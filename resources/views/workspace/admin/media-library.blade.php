@extends('layouts.workspace')
@section('title', 'Media Library')
@section('heading', 'Media Library')

@section('heading_action')
    <button type="button" data-library-open="media-upload" class="library-primary">
        <x-media-library-icon name="upload" /> Upload media
    </button>
@endsection

@section('content')
<section class="media-library" data-library aria-label="Media Library">
    @if($errors->any())
        <div class="library-error" role="alert">
            @foreach($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('admin.media-library.index') }}" class="library-toolbar" role="search">
        <input type="hidden" name="columns" value="{{ $columns }}">
        <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
        <div class="library-search">
            <label for="library-q">Title</label>
            <div class="library-search-field">
                <x-media-library-icon name="search" />
                <input type="search" name="q" id="library-q" maxlength="80" value="{{ $search }}" placeholder="Search media">
            </div>
        </div>
        <div>
            <label for="library-kind">File type</label>
            <select name="kind" id="library-kind">
                <option value="">All files</option>
                <option value="image" @selected($kind === 'image')>Images</option>
                <option value="video" @selected($kind === 'video')>Videos</option>
            </select>
        </div>
        <button type="submit" class="library-button">Search</button>
        @if($search !== '' || $kind)
            <a href="{{ route('admin.media-library.index', ['columns' => $columns]) }}" class="library-button">Clear filters</a>
        @endif
    </form>

    @if($items->isEmpty())
        <div class="library-empty">
            <x-media-library-icon name="image" />
            <h2>{{ $search !== '' || $kind ? 'No matching media' : 'No media yet' }}</h2>
        </div>
    @else
        <div class="library-grid" data-library-columns="{{ $columns }}">
            @foreach($items as $item)
                @php
                    $isVideo = str_starts_with($item->mime, 'video/');
                    $url = route('editor-media.show', $item, false);
                @endphp
                <article class="library-card" data-library-item
                    data-title="{{ $item->original_name }}" data-src="{{ $url }}"
                    data-kind="{{ $isVideo ? 'video' : 'image' }}"
                    data-format="{{ strtoupper(substr(strrchr($item->mime, '/'), 1)) }}"
                    data-size="{{ \Illuminate\Support\Number::fileSize($item->size, 1) }}"
                    data-update="{{ route('admin.media-library.update', $item, false) }}"
                    data-delete="{{ route('admin.media-library.destroy', $item, false) }}">
                    <button type="button" class="library-card-open" data-library-open="media-preview"
                        aria-label="Preview {{ $item->original_name }}" title="{{ $item->original_name }}">
                        <span class="library-thumb">
                            @if($isVideo)
                                <video src="{{ $url }}#t=0.001" preload="metadata" muted playsinline aria-hidden="true"></video>
                            @else
                                <img src="{{ $url }}" alt="" loading="lazy" decoding="async">
                            @endif
                            <span class="library-thumb-icon">
                                <x-media-library-icon :name="$isVideo ? 'play' : 'expand'" />
                            </span>
                        </span>
                        <span class="library-card-title">{{ $item->original_name }}</span>
                    </button>
                    <details class="library-actions" data-library-menu>
                        <summary class="library-icon-button" aria-label="Actions for {{ $item->original_name }}" title="Media actions">
                            <x-media-library-icon name="more" />
                        </summary>
                        <div class="library-action-list">
                            <button type="button" data-library-open="media-preview"><x-media-library-icon name="eye" /> Preview</button>
                            <button type="button" data-library-open="media-edit"><x-media-library-icon name="edit" /> Edit</button>
                            <button type="button" class="library-danger-text" data-library-open="media-delete"><x-media-library-icon name="trash" /> Delete</button>
                        </div>
                    </details>
                </article>
            @endforeach
        </div>
        @if($items->hasPages())
            <div class="library-pagination">
                <span>{{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ $items->total() }}</span>
                {{ $items->links() }}
            </div>
        @endif
    @endif

    <dialog id="media-preview" class="library-dialog library-preview-dialog" aria-labelledby="media-preview-title">
        <div class="library-dialog-heading">
            <div class="min-w-0">
                <h2 id="media-preview-title" data-library-title></h2>
                <p class="library-meta" data-library-meta></p>
            </div>
            <button type="button" class="library-icon-button" data-library-close aria-label="Close preview"><x-media-library-icon name="close" /></button>
        </div>
        <div class="library-preview-stage">
            <img alt="" hidden data-preview-image>
            <x-featured-video compact label="Media preview" />
            <p hidden data-preview-error>This file could not be previewed.</p>
        </div>
    </dialog>

    <dialog id="media-upload" class="library-dialog" aria-labelledby="media-upload-title">
        <div class="library-dialog-heading">
            <h2 id="media-upload-title">Upload media</h2>
            <button type="button" class="library-icon-button" data-library-close aria-label="Close upload"><x-media-library-icon name="close" /></button>
        </div>
        <form method="POST" action="{{ route('admin.media-library.store', [], false) }}" enctype="multipart/form-data" class="library-dialog-form" data-library-submit>
            @csrf
            <label for="library-upload-file">Image or video</label>
            <input type="file" id="library-upload-file" name="file" required accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm">
            <div class="library-form-actions">
                <button type="button" class="library-button" data-library-close>Cancel</button>
                <button type="submit" class="library-primary"><x-media-library-icon name="upload" /> Upload</button>
            </div>
        </form>
    </dialog>

    <dialog id="media-edit" class="library-dialog" aria-labelledby="media-edit-title">
        <div class="library-dialog-heading">
            <h2 id="media-edit-title">Edit media</h2>
            <button type="button" class="library-icon-button" data-library-close aria-label="Close edit"><x-media-library-icon name="close" /></button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="library-dialog-form" data-library-submit>
            @csrf
            @method('PATCH')
            <div>
                <label for="library-edit-title">Title</label>
                <input type="text" id="library-edit-title" name="title" required maxlength="190">
            </div>
            <div>
                <label for="library-replacement">Replacement file <span class="library-meta">(optional, same file type)</span></label>
                <input type="file" id="library-replacement" name="replacement">
            </div>
            <div class="library-form-actions">
                <button type="button" class="library-button" data-library-close>Cancel</button>
                <button type="submit" class="library-primary">Save changes</button>
            </div>
        </form>
    </dialog>

    <dialog id="media-delete" class="library-dialog" aria-labelledby="media-delete-title">
        <div class="library-dialog-heading">
            <h2 id="media-delete-title">Delete media</h2>
            <button type="button" class="library-icon-button" data-library-close aria-label="Close delete"><x-media-library-icon name="close" /></button>
        </div>
        <form method="POST" class="library-dialog-form" data-library-submit>
            @csrf
            @method('DELETE')
            <p class="text-sm">Permanently delete <strong class="break-words" data-library-title></strong>?</p>
            <div class="library-form-actions">
                <button type="button" class="library-button" data-library-close autofocus>Cancel</button>
                <button type="submit" class="library-danger"><x-media-library-icon name="trash" /> Delete media</button>
            </div>
        </form>
    </dialog>
</section>
@endsection
