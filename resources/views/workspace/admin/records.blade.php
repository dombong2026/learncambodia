@extends('layouts.workspace')
@section('title', $title)
@section('heading', $title)
@section('description', $description)
@section('content')
<div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <table class="w-full min-w-[680px] text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr>@foreach($columns as $column)<th scope="col" class="px-5 py-4">{{ $column }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($records as $record)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">@foreach($record as $value)<td class="max-w-sm px-5 py-4 text-slate-700 dark:text-slate-300">{{ $value }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="px-5 py-12 text-center text-slate-500">No records available.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $records->links() }}</div>
@endsection
