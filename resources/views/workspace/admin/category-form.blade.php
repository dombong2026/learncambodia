@extends('layouts.workspace')
@section('title', $mode === 'edit' ? 'Edit Category' : 'Create Category')
@section('heading', $mode === 'edit' ? 'Edit Category' : 'Create Category')
@section('description', 'Control category metadata and visibility. External exam qualifications remain informational unless independently authorized.')
@section('content')
@php $editing = $mode === 'edit'; @endphp
<form action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" method="POST" class="max-w-4xl rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    @csrf @if($editing)@method('PATCH')@endif
    <div class="grid gap-6 sm:grid-cols-2">
        <div><label for="code" class="mb-2 block text-sm font-semibold">Category code</label><input id="code" name="code" value="{{ old('code', $category->code) }}" maxlength="24" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm uppercase dark:border-slate-700 dark:bg-slate-950">@error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="name" class="mb-2 block text-sm font-semibold">Category name</label><input id="name" name="name" value="{{ old('name', $category->name) }}" maxlength="255" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="group" class="mb-2 block text-sm font-semibold">Course group</label><select id="group" name="group" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            @foreach(\App\Models\Category::GROUPS as $groupKey => $groupName)
                <option value="{{ $groupKey }}" @selected(old('group', $category->group) === $groupKey)>{{ $groupName }}</option>
            @endforeach
        </select>@error('group')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="sort_order" class="mb-2 block text-sm font-semibold">Display order</label><input id="sort_order" name="sort_order" type="number" min="0" max="99999" value="{{ old('sort_order', $category->sort_order ?? 0) }}" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('sort_order')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div class="sm:col-span-2"><label for="description" class="mb-2 block text-sm font-semibold">Accurate category description</label><textarea id="description" name="description" rows="5" maxlength="2000" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 dark:border-slate-700 dark:bg-slate-950">{{ old('description', $category->description) }}</textarea>@error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div class="sm:col-span-2"><label for="is_published" class="mb-2 block text-sm font-semibold">Publication</label><select id="is_published" name="is_published" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            <option value="0" @selected((string)old('is_published', $category->is_published ? '1' : '0') === '0')>Unpublished</option>
            <option value="1" @selected((string)old('is_published', $category->is_published ? '1' : '0') === '1')>Published</option>
        </select><p class="mt-2 text-xs leading-5 text-slate-500">Published categories appear in the public catalogue and header tray. Courses remain separately draft by default.</p>@error('is_published')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
    </div>
    <div class="mt-7 flex justify-end gap-3">
        <a href="{{ route('admin.categories.index') }}" class="rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold dark:border-slate-700">Cancel</a>
        <button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">{{ $editing ? 'Save Category' : 'Create Category' }}</button>
    </div>
</form>
@endsection
