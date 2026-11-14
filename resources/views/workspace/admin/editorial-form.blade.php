@extends('layouts.workspace')
@section('title', $editing ? 'Edit Draft' : 'New Draft')
@section('heading', $editing ? 'Edit '.ucfirst(rtrim($kind, 's')).' Draft' : 'New '.ucfirst(rtrim($kind, 's')).' Draft')
@section('description', 'Drafts remain private; eligible content needs another administrator’s approval before publication.')
@section('heading_action')
@if(!$editing || in_array($entry->status,['draft','rejected'],true))
<button type="submit" form="editorial-form" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Save draft</button>
@endif
<a href="{{ route('admin.editorial.index',['kind'=>$kind]) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold dark:border-slate-700">All</a>
@endsection
@section('content')
<form id="editorial-form" method="POST" action="{{ $editing ? route('admin.editorial.update', ['kind' => $kind, 'entry' => $entry]) : route('admin.editorial.store', ['kind' => $kind]) }}" class="max-w-5xl rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    @csrf @if($editing) @method('PATCH') @endif
    <fieldset @disabled($editing && !in_array($entry->status,['draft','rejected'],true)) class="grid gap-6">
        <div><label for="title" class="mb-2 block text-sm font-semibold">Title</label><input id="title" name="title" value="{{ old('title', $entry->title) }}" maxlength="200" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('title')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="slug" class="mb-2 block text-sm font-semibold">Slug (optional)</label><input id="slug" name="slug" value="{{ old('slug', $entry->slug) }}" maxlength="200" placeholder="Generated from title if left empty" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 font-mono text-sm dark:border-slate-700 dark:bg-slate-950">@error('slug')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="summary" class="mb-2 block text-sm font-semibold">Summary</label><textarea id="summary" name="summary" rows="2" maxlength="400" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 dark:border-slate-700 dark:bg-slate-950">{{ old('summary', $entry->summary) }}</textarea>@error('summary')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="body" class="mb-2 block text-sm font-semibold">Content (plain text)</label><textarea id="body" name="body" rows="14" maxlength="25000" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 dark:border-slate-700 dark:bg-slate-950">{{ old('body', $entry->body) }}</textarea>@error('body')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        @if($kind === 'posts')
        <div class="grid gap-6 sm:grid-cols-2">
            <fieldset class="space-y-2"><legend class="mb-2 text-sm font-bold">Categories</legend>
            @forelse($categories as $category)
                <label class="flex gap-2 text-sm"><input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', $editing ? $entry->terms->where('kind','category')->pluck('id')->all() : [])))> {{ $category->name }}</label>
            @empty<p class="text-sm text-slate-500">Create a category in Post Manager first.</p>@endforelse
            </fieldset>
            <fieldset class="space-y-2"><legend class="mb-2 text-sm font-bold">Tags</legend>
            @forelse($tags as $tag)
                <label class="flex gap-2 text-sm"><input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" @checked(in_array($tag->id, old('tag_ids', $editing ? $entry->terms->where('kind','tag')->pluck('id')->all() : [])))> {{ $tag->name }}</label>
            @empty<p class="text-sm text-slate-500">Create a tag in Post Manager first.</p>@endforelse
            </fieldset>
        </div>
        @endif
    </fieldset>
    <p class="mt-4 text-xs leading-6 text-amber-700 dark:text-amber-400">Published content requires independent review. Legal policies and pages remain private drafts unless separately released. All content is displayed as escaped plain text.</p>
    <div class="mt-6 flex justify-end gap-3">
        <a href="{{ route('admin.editorial.index', ['kind' => $kind]) }}" class="rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold dark:border-slate-700">Cancel</a>
        @if(!$editing || in_array($entry->status,['draft','rejected'],true))
        <button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">{{ $editing ? 'Save draft' : 'Create draft' }}</button>@endif
    </div>
</form>
@if($editing && in_array($kind,['posts','faqs','help-articles'],true) && in_array($entry->status,['draft','rejected'],true))
    <form method="POST" action="{{ route('admin.editorial.submit',['kind'=>$kind,'entry'=>$entry]) }}" class="mt-5 flex max-w-5xl flex-wrap items-center justify-between gap-4 rounded-3xl border border-[#ebd3a5] bg-[#fff8ed] p-6 dark:border-amber-900 dark:bg-amber-950">@csrf
        <p class="max-w-lg text-sm leading-7 text-[#805b21] dark:text-[#f6d49a]">Submit this saved entry for independent content review. The article stays hidden from the public until another administrator approves it.</p>
        <button type="submit" class="rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-bold text-white hover:bg-[#1d4b82]">Submit for review</button>
    </form>
@endif
@endsection
