@extends('layouts.workspace')
@section('title', 'My Enrollments')
@section('heading', 'My Enrollments')
@section('description', 'See the status of your course requests. Each request is private to your account.')
@section('content')
<div class="mb-5 flex justify-end"><a href="{{ route('account.courses.index') }}" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white">Browse available courses</a></div>
<div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
<table class="w-full min-w-[650px] text-left text-sm">
    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr><th class="px-5 py-4">Course</th><th class="px-5 py-4">Requested</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Action</th></tr></thead>
    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
    @forelse($enrollments as $enrollment)
        <tr><td class="px-5 py-4"><p class="font-bold">{{ $enrollment->course?->title }}</p><p class="mt-1 text-xs text-slate-500">{{ $enrollment->course?->category?->name }}</p></td>
            <td class="px-5 py-4 text-xs">{{ $enrollment->created_at->format('Y-m-d') }}</td>
            <td class="px-5 py-4 text-sm font-semibold">{{ ucfirst($enrollment->status) }}</td>
            <td class="px-5 py-4 text-right">
                @if(in_array($enrollment->status, ['pending','approved'], true))
                <form method="POST" action="{{ route('account.enrollments.cancel', $enrollment) }}">@csrf @method('DELETE')
                    <button type="submit" class="rounded-full border border-red-300 px-4 py-2 text-xs font-bold text-red-700">Cancel enrollment</button>
                </form>
                @else<span class="text-xs text-slate-500">No action</span>@endif
            </td>
        </tr>
    @empty
        <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">No enrollment requests yet.</td></tr>
    @endforelse
    </tbody>
</table></div>
<div class="mt-6">{{ $enrollments->links() }}</div>
@endsection
