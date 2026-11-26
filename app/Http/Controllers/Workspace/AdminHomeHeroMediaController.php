<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\EditorMedia;
use App\Support\FeaturedMedia;
use App\Support\HomeHeroMedia;
use App\Support\ManagedFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminHomeHeroMediaController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'kind' => ['nullable', Rule::in(['image', 'video'])],
        ]);
        $items = EditorMedia::where('context', 'library')->whereIn('mime', FeaturedMedia::MIMES)
            ->when(filled($data['q'] ?? null), fn ($query) => $query->where('original_name', 'like', '%'.$data['q'].'%'))
            ->when(filled($data['kind'] ?? null), fn ($query) => $query->where('mime', 'like', $data['kind'].'/%'))
            ->latest('id')->paginate(12)->withQueryString();

        return view('workspace.admin.home-hero-media', [
            'role' => 'admin', 'current' => HomeHeroMedia::current(), 'items' => $items,
            'search' => $data['q'] ?? '', 'kind' => $data['kind'] ?? '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(['default', 'library', 'upload'])],
            'media_id' => ['exclude_unless:source,library', 'required', 'integer', 'min:1'],
            'label' => ['exclude_if:source,default', 'nullable', 'string', 'max:200'],
            'file' => ['exclude_unless:source,upload', 'required', 'file', 'max:51200',
                'mimetypes:'.implode(',', FeaturedMedia::MIMES)],
        ]);

        if ($data['source'] === 'upload') {
            $file = $request->file('file');
            $path = ManagedFiles::store($file, 'editor-media');
            abort_unless($path, 500, 'Media upload was not saved.');
            try {
                DB::transaction(function () use ($request, $data, $file, $path): void {
                    $media = EditorMedia::create([
                        'uploaded_by' => $request->user()->id, 'context' => 'library',
                        'path' => $path, 'mime' => $file->getMimeType(),
                        'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 190),
                        'size' => $file->getSize(),
                    ]);
                    HomeHeroMedia::select($request->user(), $media->id, $data['label'] ?? null);
                });
            } catch (\Throwable $exception) {
                ManagedFiles::disk()->delete($path);
                throw $exception;
            }
        } else {
            HomeHeroMedia::select($request->user(), $data['source'] === 'library' ? (int) $data['media_id'] : null,
                $data['label'] ?? null);
        }

        return redirect()->route('admin.appearance.home-hero-media.index')
            ->with('status', 'Homepage hero media updated immediately.');
    }
}
