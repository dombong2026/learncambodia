@extends('layouts.public')
@section('title', 'Policy Center')
@section('content')
<section class="mx-auto max-w-5xl px-4 py-12 sm:px-8 sm:py-16">
    <h1 class="text-4xl font-black tracking-tight">Policy Center</h1>
    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        @forelse($policies as $policy)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-bold"><a href="{{ $policy->publicUrl() }}" class="text-blue-700 underline-offset-4 hover:underline dark:text-blue-300">{{ $policy->title }}</a></h2>
                @if($policy->summary)<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $policy->summary }}</p>@endif
                @if($policy->policy_effective_on)<p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Effective {{ $policy->policy_effective_on->toFormattedDateString() }}</p>@endif
            </article>
        @empty
            <p class="text-sm text-slate-600 dark:text-slate-300">No published policies.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $policies->links() }}</div>
</section>
@endsection
