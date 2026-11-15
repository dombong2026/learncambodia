@extends('layouts.workspace')
@php
$labels=['pages'=>'Page','posts'=>'Post','policies'=>'Policy','faqs'=>'FAQ','help-articles'=>'Help Article'];
$label=$labels[$kind] ?? 'Document';
$canEdit=!$editing || in_array($entry->status,['draft','rejected'],true);
$reviewable=in_array($kind,['posts','faqs','help-articles'],true);
@endphp
@section('title',($editing?'Edit ':'Create ').$label)
@section('heading',($editing?'Edit ':'New ').$label.' Draft')
@section('description',$reviewable ? 'Write and save privately. Publication requires approval by a different administrator.' : 'Manage a private draft. Pages and policies are not automatically published.')
@section('heading_action')
@if($canEdit)<button type="submit" form="editorial-form" class="rounded-lg bg-blue-700 px-4 py-2.5 text-xs font-bold text-white hover:bg-blue-800">Save draft</button>@endif
<a href="{{ route('admin.editorial.index',['kind'=>$kind]) }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-bold dark:border-slate-700">All {{ $label }}s</a>
@endsection
@section('content')
<form id="editorial-form" method="POST" action="{{ $editing ? route('admin.editorial.update',['kind'=>$kind,'entry'=>$entry]) : route('admin.editorial.store',['kind'=>$kind]) }}" class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_318px]">
    @csrf @if($editing) @method('PATCH') @endif
    <fieldset @disabled(!$canEdit) class="min-w-0 space-y-4 rounded-2xl border border-slate-200 p-4 sm:p-5 dark:border-slate-700">
        <div>
            <label for="title" class="mb-1.5 block text-xs font-bold">Title</label>
            <input id="title" name="title" data-document-title value="{{ old('title',$entry->title) }}" maxlength="200" required
                class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2.5 text-sm font-semibold dark:border-slate-700" placeholder="{{ $label }} title">
            @error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <label for="rich-editor-document-plain" class="text-xs font-bold">Content</label>
            <span class="text-xs text-slate-500 dark:text-slate-400">Text editor · safe HTML · images · audio · video</span>
        </div>
        <x-rich-editor id="rich-editor-document" :value="old('body',$entry->body)" :html="old('body_html',$entry->body_html)" context="editorial"/>
        @if($canEdit)
        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('admin.editorial.index',['kind'=>$kind]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold dark:border-slate-700">Cancel</a>
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-xs font-bold text-white">Save {{ $label }} Draft</button>
        </div>
        @endif
    </fieldset>
    <aside class="min-w-0 space-y-4 xl:sticky xl:top-24 xl:self-start" aria-label="Document settings">
        <fieldset @disabled(!$canEdit) class="space-y-4 rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
            <div class="flex items-center justify-between gap-3"><h2 class="text-sm font-extrabold">Document settings</h2><span class="text-xs font-bold text-amber-600">{{ ucfirst($entry->status ?: 'draft') }}</span></div>
            <div>
                <label for="slug" class="mb-1 block text-xs font-semibold">URL slug</label>
                <input id="slug" name="slug" data-editor-slug value="{{ old('slug',$entry->slug) }}" maxlength="200" placeholder="Generated from title if empty" class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-xs dark:border-slate-700">
                @error('slug')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="summary" class="mb-1 block text-xs font-semibold">Summary</label>
                <textarea id="summary" name="summary" data-editor-summary rows="3" maxlength="400" class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-xs dark:border-slate-700">{{ old('summary',$entry->summary) }}</textarea>
                @error('summary')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <details open class="border-t border-slate-200 pt-3 dark:border-slate-700">
                <summary class="cursor-pointer text-xs font-bold">Search appearance</summary>
                <div class="mt-3 space-y-3">
                    <label for="seo_title" class="block text-xs font-semibold">SEO title</label>
                    <input id="seo_title" name="seo_title" data-seo-title value="{{ old('seo_title',$entry->seo_title) }}" maxlength="200" placeholder="Use document title" class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-xs dark:border-slate-700">
                    <label for="seo_description" class="block text-xs font-semibold">SEO description</label>
                    <textarea id="seo_description" name="seo_description" data-seo-description rows="3" maxlength="320" placeholder="Use summary" class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-xs dark:border-slate-700">{{ old('seo_description',$entry->seo_description) }}</textarea>
                    <div class="border-t border-slate-200 pt-2 dark:border-slate-700" aria-label="Live search snippet preview">
                        <p data-seo-preview-title class="break-words text-sm font-semibold text-blue-700 dark:text-blue-400">{{ $entry->seo_title ?: ($entry->title ?: 'Document title') }}</p>
                        <p data-seo-preview-description class="mt-1 break-words text-xs text-slate-500">{{ $entry->seo_description ?: ($entry->summary ?: 'Add a summary for your search preview.') }}</p>
                    </div>
                </div>
            </details>
            @if($kind==='posts')
            <details open class="border-t border-slate-200 pt-3 dark:border-slate-700">
                <summary class="cursor-pointer text-xs font-bold">Categories</summary>
                <div class="mt-2 max-h-36 space-y-2 overflow-auto">
                    @forelse($categories as $category)
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id,old('category_ids',$editing?$entry->terms->where('kind','category')->pluck('id')->all():[])))>{{ $category->name }}</label>
                    @empty<a href="{{ route('admin.post-terms.create',['kind'=>'categories']) }}" class="text-xs text-blue-600 underline">Create category</a>@endforelse
                </div>
                <a href="{{ route('admin.post-terms.index',['kind'=>'categories']) }}" class="mt-2 inline-block text-xs text-blue-600 underline">Manage categories</a>
            </details>
            <details open class="border-t border-slate-200 pt-3 dark:border-slate-700">
                <summary class="cursor-pointer text-xs font-bold">Tags</summary>
                <div class="mt-2 max-h-36 space-y-2 overflow-auto">
                    @forelse($tags as $tag)
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" @checked(in_array($tag->id,old('tag_ids',$editing?$entry->terms->where('kind','tag')->pluck('id')->all():[])))>{{ $tag->name }}</label>
                    @empty<a href="{{ route('admin.post-terms.create',['kind'=>'tags']) }}" class="text-xs text-blue-600 underline">Create tag</a>@endforelse
                </div>
                <a href="{{ route('admin.post-terms.index',['kind'=>'tags']) }}" class="mt-2 inline-block text-xs text-blue-600 underline">Manage tags</a>
            </details>
            @endif
        </fieldset>
        <div class="space-y-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
            <h2 class="text-sm font-extrabold">Preview & workflow</h2>
            <p data-editor-words-summary class="text-xs text-slate-500">Document word count updates as you write.</p>
            @if($editing)
                <a href="{{ route('admin.editorial.preview',['kind'=>$kind,'entry'=>$entry]) }}" class="block rounded-lg border border-slate-300 px-3 py-2 text-center text-xs font-bold dark:border-slate-700">Preview saved document</a>
            @endif
            @if($reviewable)
                <p class="text-xs leading-5 text-slate-500">A different administrator must approve the saved draft before public publication.</p>
                <a href="{{ route('admin.editorial.reviews',['kind'=>$kind]) }}" class="block text-xs font-semibold text-blue-600 underline">Review queue</a>
            @else
                <p class="text-xs leading-5 text-slate-500">Pages and legal policies remain private until a separate approved publishing workflow exists.</p>
            @endif
        </div>
    </aside>
</form>
@if($editing && $reviewable && $canEdit)
<form method="POST" action="{{ route('admin.editorial.submit',['kind'=>$kind,'entry'=>$entry]) }}" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 px-4 py-3 dark:border-amber-800">
    @csrf
    <p class="max-w-xl text-xs leading-5 text-slate-600 dark:text-slate-300">Submit this saved draft for independent review. It stays private until approved.</p>
    <button type="submit" class="rounded-lg border border-amber-500 px-4 py-2 text-xs font-bold text-amber-800 dark:text-amber-300">Submit for review</button>
</form>
@endif
@endsection
