@extends('layouts.public')
@section('canonical', e($exam->publicUrl()))
@section('title', $exam->title)
@section('content')
    @php
        $label = $exam->kind === 'quiz' ? 'quiz' : 'exam';
        $price = match($quote['model']) {
            'paid' => \App\Support\CoursePricing::formatAmount($quote['amount_minor'], $quote['currency']),
            'membership' => $quote['plan'].' membership',
            default => 'Free',
        };
        $canStart = $quote['model'] === 'free' || ($access?->status === 'approved' && $access->matches($quote));
        $active = $history?->first(fn ($attempt) => !$attempt->submitted_at && $attempt->expires_at->isFuture());
    @endphp
    <x-learning-hero :title="$exam->title" :subtitle="$exam->kind === 'quiz' ? 'Practice quiz' : 'Timed exam'" icon="clipboard-check" />
    <x-learning-section title="Assessment details" tone="white">
        <p class="font-semibold">{{ $exam->type?->name }} · {{ $exam->time_limit }} min · {{ $exam->attempt_limit }} {{ Str::plural('attempt', $exam->attempt_limit) }} · {{ $price }}</p>
        @if(session('status'))<p class="mt-3" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<p class="mt-3" role="alert">{{ $errors->first() }}</p>@endif
        <div class="mt-5">
            @guest
                <a class="font-bold underline" href="{{ route('login') }}">Sign in as a learner to take this {{ $label }}</a>
            @elseif(auth()->user()->role === 'student')
                @if($active)
                    <a class="rounded-xl bg-blue-700 px-5 py-3 font-bold text-white" href="{{ route('account.assessment-attempts.show', $active) }}">Continue {{ $label }}</a>
                @elseif($canStart && $history->count() < $exam->attempt_limit)
                    <form method="post" action="{{ route('account.assessment-attempts.start', $exam) }}">@csrf
                        <button class="rounded-xl bg-blue-700 px-5 py-3 font-bold text-white">Start {{ $label }}</button>
                    </form>
                @elseif($canStart)
                    <p>All available attempts have been used.</p>
                @elseif($access?->status === 'pending' && $access->matches($quote))
                    <p>Access request pending administrator verification.</p>
                @else
                    <form method="post" action="{{ route('account.assessment-access.request', $exam) }}">@csrf
                        <input type="hidden" name="pricing_quote" value="{{ json_encode($quote, JSON_THROW_ON_ERROR) }}">
                        <p class="mb-3">An administrator verifies {{ $quote['model'] === 'paid' ? 'payment' : 'membership' }} before granting access.</p>
                        <button class="rounded-xl bg-blue-700 px-5 py-3 font-bold text-white">Request access</button>
                    </form>
                @endif
                @if($history->isNotEmpty())
                    <h3 class="mt-8 font-bold">Your attempts</h3>
                    <ul class="mt-3 space-y-2">
                        @foreach($history as $past)
                            <li><a class="underline" href="{{ route('account.assessment-attempts.show', $past) }}">Attempt {{ $history->count() - $loop->index }}</a>
                                · {{ $past->submitted_at ? ($past->score === null ? 'Submitted' : $past->score.'%') : ($past->expires_at->isFuture() ? 'In progress' : 'Expired') }}</li>
                        @endforeach
                    </ul>
                @endif
            @else
                <p>Students take assessments from their learner accounts.</p>
            @endguest
        </div>
    </x-learning-section>
@endsection
