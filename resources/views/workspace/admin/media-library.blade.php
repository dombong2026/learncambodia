@extends('layouts.workspace')
@section('title', 'Media Library')
@section('heading', 'Media Library')
@section('content')
<div class="space-y-6" data-media-library-page data-upload-url="{{ route('admin.media-library.store', [], false) }}">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <form method="GET" class="flex flex-wrap items-end gap-2" role="search">
            <div><label for="library-q" class="mb-1 block text-xs font-semibold">Filename</label>
                <input type="search" name="q" id="library-q" maxlength="80" value="{{ $search }}" class="rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-sm dark:border-slate-700">
            </div>
            <div><label for="library-kind" class="mb-1 block text-xs font-semibold">File type</label>
                <select name="kind" id="library-kind" class="rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                    <option value="">All</option><option value="image" @selected($kind === 'image')>Images</option><option value="video" @selected($kind === 'video')>Videos</option>
                </select>
            </div>
            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold dark:border-slate-600">Search</button>
        </form>
        <label class="block text-xs font-semibold">Upload image or video
            <input type="file" data-media-upload accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm" class="mt-2 block max-w-full text-xs">
        </label>
    </div>
    <p role="status" data-media-status class="text-sm text-slate-600 dark:text-slate-300"></p>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($items as $item)
            <div class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
                @if(str_starts_with($item->mime, 'image/'))
                    <img src="{{ route('editor-media.show', $item) }}" loading="lazy" alt="" class="aspect-video w-full bg-slate-950 object-contain">
                @else
                    <x-featured-video :src="route('editor-media.show', $item)" :label="$item->original_name"/>
                @endif
                <p class="break-words p-3 text-sm font-semibold">{{ $item->original_name }}</p>
            </div>
        @empty
            <p class="text-sm text-slate-500">No matching files.</p>
        @endforelse
    </div>
    {{ $items->links() }}
</div>
@endsection
