@extends('layouts.workspace')
@section('title', 'Policy version history')
@section('heading', 'Policy version history')
@section('heading_action')
<a href="{{ route('admin.editorial.edit', ['kind' => 'policies', 'entry' => $entry]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold dark:border-slate-700">Back to policy</a>
@endsection
@section('content')
@if(isset($revision))
    <article class="mx-auto max-w-4xl space-y-4 rounded-2xl border border-slate-200 p-6 dark:border-slate-700">
        <a class="text-sm font-semibold text-blue-700 underline dark:text-blue-300" href="{{ route('admin.policy-history.index', $entry) }}">All versions</a>
        <h2 class="text-2xl font-bold">{{ $snapshot->title }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $snapshot->policy_version ?: 'Unnumbered version' }} · Archived {{ $revision->created_at->toDayDateTimeString() }}</p>
        @if($snapshot->policy_effective_on)<p class="text-sm">Effective {{ $snapshot->policy_effective_on->toFormattedDateString() }}</p>@endif
        <p>{{ $snapshot->summary }}</p>
        <x-editorial-featured-media :entry="$snapshot"/>
        <div class="rich-public-content break-words leading-8">
            @if($snapshot->body_html){!! \App\Support\EditorHtml::clean($snapshot->body_html) !!}
            @else<p class="whitespace-pre-line">{{ $snapshot->body }}</p>@endif
        </div>
    </article>
@else
    <div class="mx-auto max-w-4xl space-y-3">
        <h2 class="text-lg font-bold">{{ $entry->title }}</h2>
        @forelse($revisions as $version)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                <div><p class="text-sm font-bold">{{ $version->snapshot['policy_version'] ?: 'Unnumbered version' }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $version->created_at->toDayDateTimeString() }} · {{ $version->author?->name ?? 'Unknown author' }}</p>
                </div>
                <a class="text-sm font-semibold text-blue-700 underline dark:text-blue-300" href="{{ route('admin.policy-history.show', [$entry, $version]) }}">View version</a>
            </div>
        @empty
            <p class="text-sm text-slate-500 dark:text-slate-400">Older published versions will appear here when a policy is updated or withdrawn.</p>
        @endforelse
        {{ $revisions->links() }}
    </div>
@endif
@endsection
