<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use App\Models\EditorMedia;
use App\Support\FeaturedMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EditorMediaController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'kind' => ['nullable', Rule::in(['image', 'video'])],
        ]);
        $search = $filters['q'] ?? '';
        $kind = $filters['kind'] ?? null;
        $items = EditorMedia::query()->where('context', 'library')
            ->when($kind, fn ($query) => $query->where('mime', 'like', $kind.'/%'))
            ->when($search !== '', fn ($query) => $query->where('original_name', 'like', '%'.$search.'%'))
            ->latest('id')->paginate(12)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'items' => $items->getCollection()->map($this->describe(...)),
                'page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ]);
        }

        return view('workspace.admin.media-library', compact('items', 'search', 'kind'));
    }

    public function uploadToLibrary(Request $request): JsonResponse
    {
        $request->merge(['context' => 'library']);

        return $this->storeUpload($request, true);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->storeUpload($request, false);
    }

    private function storeUpload(Request $request, bool $library): JsonResponse
    {
        $data = $request->validate([
            'context' => ['required', Rule::in($library ? ['library'] : ['editorial', 'ticket'])],
            'file' => ['required', 'file', $library ? 'max:51200' : 'max:12288',
                'mimetypes:'.implode(',', $library
                    ? FeaturedMedia::MIMES
                    : [...FeaturedMedia::MIMES, 'audio/mpeg', 'audio/ogg', 'audio/wav'])],
        ]);
        abort_if($data['context'] !== 'ticket' && $request->user()->role !== 'admin', 403);

        // Cap unclaimed private media rather than enabling unlimited anonymous staging.
        if (!$library) {
            $unclaimed = EditorMedia::query()->where('context', $data['context'])->where('uploaded_by', $request->user()->id)
                ->whereNull('content_entry_id')->whereNull('support_ticket_id')
                ->where('created_at', '>=', now()->subDay())->count();
            abort_if($unclaimed >= 20, 429, 'Save your current draft before uploading more files.');
        }

        $file = $request->file('file');
        $path = $file->store('editor-media', 'local');
        abort_unless($path, 500, 'Media upload was not saved.');

        try {
            $media = EditorMedia::create([
                'uploaded_by' => $request->user()->id,
                'context' => $data['context'],
                'path' => $path,
                'mime' => $file->getMimeType(),
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 190),
                'size' => $file->getSize(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return response()->json($this->describe($media), 201);
    }

    private function describe(EditorMedia $media): array
    {
        return [
            'id' => $media->id,
            'url' => route('editor-media.show', $media, false),
            'mime' => $media->mime,
            'name' => $media->original_name,
        ];
    }

    public function show(Request $request, EditorMedia $media): BinaryFileResponse|StreamedResponse
    {
        $viewer = $request->user();
        if ($media->context === 'library') {
            $published = ContentEntry::query()->where('featured_media_id', $media->id)
                ->where('status', 'published')->whereIn('featured_kind', ['image', 'video'])->exists();
            abort_unless($published || ($viewer && $viewer->role === 'admin'), 403);
        } elseif ($media->context === 'editorial') {
            $approved = $media->content_entry_id !== null &&
                ContentEntry::query()->whereKey($media->content_entry_id)
                    ->where('status', 'published')->whereIn('type', [...ContentEntry::DIRECT_PUBLICATION_TYPES, 'faq'])->exists();
            abort_unless($approved || ($viewer && $viewer->role === 'admin'), 403);
        } else {
            $ticket = $media->support_ticket_id
                ? \App\Models\SupportTicket::find($media->support_ticket_id) : null;
            abort_unless($viewer && (
                ($ticket && ($viewer->role === 'admin' || $ticket->user_id === $viewer->id)) ||
                (!$ticket && $media->support_ticket_id === null && $media->uploaded_by === $viewer->id)
            ), 403);
        }
        $disk = Storage::disk('local');
        abort_unless($disk->exists($media->path), 404);
        $headers = [
            'Content-Type' => $media->mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];

        if (str_starts_with($media->mime, 'video/')) {
            return new BinaryFileResponse($disk->path($media->path), 200, $headers, false);
        }

        return $disk->response($media->path, null, $headers);
    }
}
