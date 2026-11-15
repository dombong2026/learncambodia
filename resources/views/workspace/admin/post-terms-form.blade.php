@extends('layouts.workspace')
@section('title',$editing?'Edit '.$kind:'Create '.$kind)
@section('heading',$editing?'Edit '.ucfirst($kind):'Create '.ucfirst($kind))
@section('heading_action')
<button type="submit" form="post-term-form" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Save</button>
<a href="{{ route('admin.post-terms.index',['kind'=>$kind]) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold">All</a>
@endsection
@section('content')
<form id="post-term-form" method="POST" action="{{ $editing?route('admin.post-terms.update',['kind'=>$kind,'term'=>$term]):route('admin.post-terms.store',['kind'=>$kind]) }}" class="max-w-3xl space-y-5 rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-700 dark:bg-slate-900">@csrf @if($editing) @method('PATCH') @endif
@foreach(['name'=>'Name','slug'=>'Slug (optional)','description'=>'Description'] as $field=>$label)
<div><label for="{{ $field }}" class="mb-2 block text-sm font-bold">{{ $label }}</label>
@if($field==='description')<textarea id="{{ $field }}" name="{{ $field }}" maxlength="500" rows="3" class="w-full rounded-xl border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">{{ old($field,$term->$field) }}</textarea>
@else<input id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$term->$field) }}" maxlength="{{ $field==='name'?120:160 }}" @if($field==='name') required @endif class="w-full rounded-xl border border-slate-300 bg-white p-3 dark:border-slate-700 dark:bg-slate-950">@endif
@error($field)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
@endforeach
<button type="submit" class="rounded-xl bg-blue-700 px-6 py-3 text-sm font-bold text-white">Save</button>
</form>
@endsection