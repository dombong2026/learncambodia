@extends('layouts.workspace')
@section('title','Post '.ucfirst($kind))
@section('heading','Post '.ucfirst($kind))
@section('description','Create and manage real post taxonomies. Deleting a term removes its associations from posts.')
@section('heading_action')
<a href="{{ route('admin.post-terms.create',['kind'=>$kind]) }}" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Create</a>
@endsection
@section('content')
<form method="GET" class="mb-5 flex gap-2" role="search"><label class="sr-only" for="term-q">Search</label><input id="term-q" name="q" maxlength="80" value="{{ $query }}" placeholder="Search {{ $kind }}" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900"><button class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold">Search</button></form>
<div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"><table class="w-full min-w-[570px] text-left text-sm"><thead><tr class="border-b border-slate-200 dark:border-slate-700"><th class="p-5">Name</th><th>Slug</th><th>Description</th><th class="text-right p-5">Actions</th></tr></thead><tbody>
@forelse($terms as $term)<tr class="border-t border-slate-200 dark:border-slate-700"><td class="p-5 font-bold">{{ $term->name }}</td><td>{{ $term->slug }}</td><td>{{ $term->description }}</td><td class="p-5 text-right"><a href="{{ route('admin.post-terms.edit',['kind'=>$kind,'term'=>$term]) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold">Edit</a><form class="inline" method="POST" action="{{ route('admin.post-terms.destroy',['kind'=>$kind,'term'=>$term]) }}" onsubmit="return confirm('Delete this term?')">@csrf @method('DELETE')<button class="ml-2 rounded-xl border border-red-300 px-4 py-2 text-xs font-bold text-red-700">Delete</button></form></td></tr>
@empty<tr><td colspan="4" class="p-8 text-sm text-slate-500">No {{ $kind }} found. Use Create to add one.</td></tr>@endforelse
</tbody></table></div><div class="mt-6">{{ $terms->links() }}</div>
@endsection