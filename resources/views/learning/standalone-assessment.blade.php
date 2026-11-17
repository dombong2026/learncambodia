@extends('layouts.public')
@section('canonical', e($exam->publicUrl()))
@section('title', $exam->title)
@section('content')
@php
    $isQuiz = $exam->kind === 'quiz';
    $label = $isQuiz ? 'quiz' : 'exam';
    $price = match($quote['model']) {
        'paid' => \App\Support\CoursePricing::formatAmount($quote['amount_minor'], $quote['currency']),
        'membership' => $quote['plan'].' membership',
        default => 'Free',
    };
    $canStart = $quote['model'] === 'free' || ($access?->status === 'approved' && $access->matches($quote));
    $active = $history?->first(fn ($attempt) => !$attempt->submitted_at && $attempt->expires_at->isFuture());
@endphp
<x-detail-hero :title="$exam->title" :entry="$isQuiz ? $exam : null" :icon="$isQuiz ? 'list-check' : 'clipboard-check'" :label="__('detail.'.$label)">
    <x-slot:meta>
        @if($exam->type)<li>{{ $exam->type->name }}</li>@endif
        <li>{{ trans_choice('detail.minutes', $exam->time_limit) }}</li>
        <li>{{ trans_choice('detail.attempts', $exam->attempt_limit) }}</li>
    </x-slot:meta>
    <x-slot:rail>
        <h2 class="text-xl font-black">{{ __('frontend.start_learning') }}</h2>
        <p class="mt-3 text-sm font-bold text-[#f6cd7f]">{{ $price }}</p>
        @if(session('status'))<p class="mt-3 text-sm text-[#d1e0ef]" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<p class="mt-3 text-sm text-red-200" role="alert">{{ $errors->first() }}</p>@endif
        @guest
            <a class="mt-5 block rounded-xl bg-[#efbe62] px-5 py-3 text-center text-xs font-black text-[#0b2f5c] hover:bg-[#ffdc91]" href="{{ \App\Support\ReadingReturn::authUrl(request(), 'login') }}">Sign in as a learner to take this {{ $label }}</a>
        @elseif(auth()->user()->role === \App\Models\User::ROLE_STUDENT)
            @if($active)
                <a class="mt-5 block rounded-xl bg-[#efbe62] px-5 py-3 text-center text-xs font-black text-[#0b2f5c] hover:bg-[#ffdc91]" href="{{ route('account.assessment-attempts.show', $active) }}">Continue {{ $label }}</a>
            @elseif($canStart && $history->count() < $exam->attempt_limit)
                <form method="post" action="{{ route('account.assessment-attempts.start', $exam) }}" class="mt-5">
                    @csrf
                    <button class="w-full rounded-xl bg-[#efbe62] px-5 py-3 text-center text-xs font-black text-[#0b2f5c] hover:bg-[#ffdc91]">Start {{ $label }}</button>
                </form>
            @elseif($canStart)
                <p class="mt-4 text-sm leading-6 text-[#d1e0ef]">All available attempts have been used.</p>
            @elseif($access?->status === 'pending' && $access->matches($quote))
                <p class="mt-4 text-sm leading-6 text-[#d1e0ef]">Access request pending administrator verification.</p>
            @else
                <form method="post" action="{{ route('account.assessment-access.request', $exam) }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="pricing_quote" value="{{ json_encode($quote, JSON_THROW_ON_ERROR) }}">
                    <p class="text-sm leading-6 text-[#d1e0ef]">An administrator verifies {{ $quote['model'] === 'paid' ? 'payment' : 'membership' }} before granting access.</p>
                    <button class="mt-4 w-full rounded-xl bg-[#efbe62] px-5 py-3 text-center text-xs font-black text-[#0b2f5c] hover:bg-[#ffdc91]">Request access</button>
                </form>
            @endif
        @else
            <p class="mt-4 text-sm leading-6 text-[#d1e0ef]">Students take assessments from their learner accounts.</p>
        @endguest
        <x-detail-share :url="$exam->publicUrl()" :title="$exam->title" />
    </x-slot:rail>
</x-detail-hero>

<div class="detail-grid mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    <div class="min-w-0 space-y-5">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#152e50]">
            <h2 class="text-xl font-black text-[#12355e] dark:text-white">{{ __('detail.assessment_details') }}</h2>
            <p class="mt-3 text-sm leading-6 text-slate-700 dark:text-slate-200">{{ __('detail.passing_score', ['score' => $exam->passing_percent]) }}</p>
        </section>
        <x-learning-detail-metadata :record="$exam" :kind="$label" />
        @if($history?->isNotEmpty())
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#152e50]">
                <h2 class="text-xl font-black text-[#12355e] dark:text-white">Your attempts</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach($history as $past)
                        <li><a class="font-bold underline" href="{{ route('account.assessment-attempts.show', $past) }}">Attempt {{ $history->count() - $loop->index }}</a>
                            · {{ $past->submitted_at ? ($past->score === null ? 'Submitted' : $past->score.'%') : ($past->expires_at->isFuture() ? 'In progress' : 'Expired') }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
    <aside class="min-w-0 space-y-5">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#152e50]">
            <h2 class="text-xl font-black text-[#12355e] dark:text-white">{{ __('pages.study_process') }}</h2>
            <p class="mt-3 text-sm leading-6 text-slate-700 dark:text-slate-200">{{ __('detail.assessment_getting_started') }}</p>
        </section>
        <section class="rounded-3xl bg-[#0c305e] p-6 text-white">
            <h2 class="text-lg font-black">{{ __('pages.explore_related') }}</h2>
            <a class="mt-4 block rounded-xl bg-[#efbe62] px-5 py-3 text-center text-xs font-black text-[#0b2f5c] hover:bg-[#ffdc91]" href="{{ route($isQuiz ? 'assessments.quizzes' : 'assessments.exams') }}">{{ __($isQuiz ? 'detail.browse_quizzes' : 'detail.browse_exams') }}</a>
        </section>
    </aside>
</div>
@endsection
