@extends('layouts.workspace')
@section('title', $title)
@section('heading', $title)
@section('description', $description)
@section('content')
@if(isset($userFilters))
    <form action="{{ route('admin.users.index') }}" method="GET" class="mb-5 flex flex-wrap items-end gap-3" aria-label="Filter users">
        <label class="grid flex-1 gap-1 text-sm font-semibold">
            Search users
            <input name="q" value="{{ $userFilters['q'] }}" maxlength="80" placeholder="Name, email, or user ID" class="min-w-0 rounded-xl border border-slate-300 px-4 py-2.5 dark:border-slate-700 dark:bg-slate-900">
        </label>
        <label class="grid gap-1 text-sm font-semibold">
            Role
            <select name="role" class="rounded-xl border border-slate-300 px-4 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                <option value="">All roles</option>
                @foreach(\App\Support\AdminAccess::ROLES as $key => $label)
                    <option value="{{ $key }}" @selected($userFilters['role'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm font-semibold">
            Status
            <select name="status" class="rounded-xl border border-slate-300 px-4 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                <option value="">Active and deactivated</option>
                @foreach(['active', 'deactivated', 'deleted'] as $status)
                    <option value="{{ $status }}" @selected($userFilters['status'] === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </label>
        <button class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold dark:border-slate-700">Filter</button>
        @if($userFilters['q'] || $userFilters['role'] || $userFilters['status'])
            <a href="{{ route('admin.users.index') }}" class="rounded-xl px-4 py-2.5 text-sm text-blue-700 dark:text-blue-300">Clear</a>
        @endif
    </form>
@endif
<div @class([
    'learning-manager learning-manager-scroll' => isset($userFilters),
    'overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900' => ! isset($userFilters),
])>
    <table class="w-full min-w-[680px] text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr>@foreach($columns as $column)<th scope="col" class="px-5 py-4">{{ $column }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($records as $record)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">@foreach($record as $value)<td @class([
                    'max-w-sm px-5 py-4 text-slate-700 dark:text-slate-300',
                    'learning-manager-actions' => is_array($value) && isset($value['account']),
                ])>@if(is_array($value) && isset($value['account']))
                    @include('workspace.admin.user-row-actions', ['account' => $value['account']])
                @elseif(is_array($value))
                    <a href="{{ $value['url'] }}" class="font-semibold text-blue-700 hover:underline dark:text-blue-300">{{ $value['value'] }}</a>
                @else
                    {{ $value }}
                @endif</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="px-5 py-12 text-center text-slate-500">No records available.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $records->links() }}</div>
@endsection
