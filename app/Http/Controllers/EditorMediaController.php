<?php

namespace App\Http\Controllers;

use App\Models\EditorMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EditorMediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'context' => ['required', Rule::in(['editorial','ticket'])],
            'file' => ['required', 'file', 'max:12288', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,audio/mpeg,audio/ogg,audio/wav,video/mp4,video/webm'],
        ]);
        abort_if($data['context'] === 'editorial' && $request->user()->role !== 'admin', 403);

        // Cap unclaimed private media rather than enabling unlimited anonymous staging.
        $unclaimed = EditorMedia::query()->where('uploaded_by', $request->user()->id)
            ->whereNull('content_entry_id')->whereNull('support_ticket_id')
            ->where('created_at', '>=', now()->subDay())->count();
        abort_if($unclaimed >= 20, 429, 'Save your current draft before uploading more files.');

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

        return response()->json([
            'id' => $media->id,
            'url' => route('editor-media.show', $media, false),
            'mime' => $media->mime,
            'name' => $media->original_name,
        ], 201);
    }

    public function show(Request $request, EditorMedia $media): StreamedResponse
    {
        $viewer = $request->user();
        if ($media->context === 'editorial') {
            $approved = $media->content_entry_id !== null &&
                \App\Models\ContentEntry::query()->whereKey($media->content_entry_id)
                    ->where('status', 'published')->whereIn('type', ['post','faq','help_article'])->exists();
            abort_unless($approved || ($viewer && $viewer->role === 'admin'), 403);
        } else {
            $ticket = $media->support_ticket_id
                ? \App\Models\SupportTicket::find($media->support_ticket_id) : null;
            abort_unless($viewer && (
                ($ticket && ($viewer->role === 'admin' || $ticket->user_id === $viewer->id)) ||
                (!$ticket && $media->support_ticket_id === null && $media->uploaded_by === $viewer->id)
            ), 403);
        }
        abort_unless(Storage::disk('local')->exists($media->path), 404);
        return Storage::disk('local')->response($media->path, null, [
            'Content-Type' => $media->mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
