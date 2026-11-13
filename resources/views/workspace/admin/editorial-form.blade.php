@extends('layouts.workspace')
@section('title', $editing ? 'Edit Draft' : 'New Draft')
@section('heading', $editing ? 'Edit '.ucfirst(rtrim($kind, 's')).' Draft' : 'New '.ucfirst(rtrim($kind, 's')).' Draft')
@section('description', 'Drafts remain private. Posts may be submitted for independent administrator approval; legal policies and pages remain private until separately approved.')
@section('content')
<form method="POST" action="{{ $editing ? route('admin.editorial.update', ['kind' => $kind, 'entry' => $entry]) : route('admin.editorial.store', ['kind' => $kind]) }}" class="max-w-5xl rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    @csrf @if($editing) @method('PATCH') @endif
    <fieldset @disabled($editing && !in_array($entry->status,['draft','rejected'],true)) class="grid gap-6">
        <div><label for="title" class="mb-2 block text-sm font-semibold">Title</label><input id="title" name="title" value="{{ old('title', $entry->title) }}" maxlength="200" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('title')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="slug" class="mb-2 block text-sm font-semibold">Slug (optional)</label><input id="slug" name="slug" value="{{ old('slug', $entry->slug) }}" maxlength="200" placeholder="Generated from title if left empty" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 font-mono text-sm dark:border-slate-700 dark:bg-slate-950">@error('slug')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="summary" class="mb-2 block text-sm font-semibold">Summary</label><textarea id="summary" name="summary" rows="2" maxlength="400" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 dark:border-slate-700 dark:bg-slate-950">{{ old('summary', $entry->summary) }}</textarea>@error('summary')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="body" class="mb-2 block text-sm font-semibold">Content (plain text)</label><textarea id="body" name="body" rows="14" maxlength="25000" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 dark:border-slate-700 dark:bg-slate-950">{{ old('body', $entry->body) }}</textarea>@error('body')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
    </fieldset>
    <p class="mt-4 text-xs leading-6 text-amber-700 dark:text-amber-400">Articles require independent editorial approval. Legal policies and other pages still remain drafts. All content is displayed as escaped plain text.</p>
    <div class="mt-6 flex justify-end gap-3">
        <a href="{{ route('admin.editorial.index', ['kind' => $kind]) }}" class="rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold dark:border-slate-700">Cancel</a>
        @if(!$editing || in_array($entry->status,['draft','rejected'],true))
        <button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">{{ $editing ? 'Save draft' : 'Create draft' }}</button>@endif
    </div>
</form>
@if($editing && $kind==='posts' && in_array($entry->status,['draft','rejected'],true))
    <form method="POST" action="{{ route('admin.editorial.submit',['kind'=>'posts','entry'=>$entry]) }}" class="mt-5 flex max-w-5xl flex-wrap items-center justify-between gap-4 rounded-3xl border border-[#ebd3a5] bg-[#fff8ed] p-6 dark:border-amber-900 dark:bg-amber-950">@csrf
        <p class="max-w-lg text-sm leading-7 text-[#805b21] dark:text-[#f6d49a]">Submit this saved article for independent academic and editorial review. The article stays hidden from the public until another administrator approves it.</p>
        <button type="submit" class="rounded-xl bg-[#0c2d5b] px-5 py-3 text-sm font-bold text-white hover:bg-[#1d4b82]">Submit article for review</button>
    </form>
@endif
@endsection
