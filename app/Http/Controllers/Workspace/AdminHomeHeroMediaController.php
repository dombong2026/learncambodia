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

        $current = HomeHeroMedia::current();
        $parts = [...range(1, 9), 11];
        $names = array_map(fn ($part) => 'Learn Cambodia part '.$part.'.mp4', $parts);
        $matches = EditorMedia::where('context', 'library')->where('mime', 'video/mp4')
            ->whereIn('original_name', $names)->get()->groupBy('original_name');
        $preset = collect($names)->map(fn ($name) => $matches->get($name)?->count() === 1
            ? $matches->get($name)->first() : null);
        $describe = fn ($media) => ['id' => $media->id, 'name' => $media->original_name];

        return view('workspace.admin.home-hero-media', [
            'playlistItems' => ($current['kind'] ?? null) === 'playlist' ? $current['items']->map($describe)->all() : [],
            'promoPreset' => $preset->contains(null) ? [] : $preset->map($describe)->all(),
            'playlistOptions' => ($current['kind'] ?? null) === 'playlist' ? $current['options'] : HomeHeroMedia::OPTIONS,
            'role' => 'admin', 'current' => $current, 'items' => $items,
            'search' => $data['q'] ?? '', 'kind' => $data['kind'] ?? '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(['default', 'library', 'upload', 'playlist'])],
            'playlist_ids' => ['exclude_unless:source,playlist', 'required', 'array', 'min:2', 'max:20'],
            'playlist_ids.*' => ['integer', 'min:1', 'distinct'],
            'options' => ['exclude_unless:source,playlist', 'required', 'array:autoplay,muted,advance,loop,shuffle,controls,show_list'],
            'options.*' => ['required', 'boolean'],
            'media_id' => ['exclude_unless:source,library', 'required', 'integer', 'min:1'],
            'label' => ['exclude_if:source,default', 'nullable', 'string', 'max:200'],
            'file' => ['exclude_unless:source,upload', 'required', 'file', 'max:51200',
                'mimetypes:'.implode(',', FeaturedMedia::MIMES)],
        ]);

        if ($data['source'] === 'playlist') {
            $options = [];
            foreach (HomeHeroMedia::OPTIONS as $key => $default) {
                $options[$key] = (bool) ($data['options'][$key] ?? $default);
            }
            HomeHeroMedia::selectPlaylist($request->user(), $data['playlist_ids'], $options, $data['label'] ?? null);
        } elseif ($data['source'] === 'upload') {
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
