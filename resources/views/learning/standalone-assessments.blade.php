@extends('layouts.public')
@php($title = $kind === 'quiz' ? 'Quick quizzes' : 'Standalone exams')
@section('title', $title)
@section('content')
    <x-learning-hero :title="$title" :subtitle="$kind === 'quiz' ? 'Practice with short quizzes and get feedback as you go.' : 'Take published assessments on your own schedule.'" icon="clipboard-check" />
    <x-learning-section :title="$kind === 'quiz' ? 'Available quizzes' : 'Available exams'" tone="white">
        <nav class="mb-5 flex flex-wrap gap-4 font-semibold">
            <a href="{{ route('assessments.exams') }}" @if($kind === 'exam') aria-current="page" @endif>Standalone exams</a>
            <a href="{{ route('assessments.quizzes') }}" @if($kind === 'quiz') aria-current="page" @endif>Quick quizzes</a>
            <a href="{{ route('learning.exams') }}">Course assessments</a>
        </nav>
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse($items as $item)
                @php($quote = \App\Support\CoursePricing::quote($item->pricing))
                <x-learning-card :href="$item->publicUrl()" :title="$item->title" icon="clipboard-check"
                    :eyebrow="$item->type?->name" :meta="$item->time_limit.' min · '.ucfirst($quote['model'])" :action="$kind === 'quiz' ? 'View quiz' : 'View exam'" />
            @empty
                <p>There are no published {{ $kind === 'quiz' ? 'quizzes' : 'exams' }} yet.</p>
            @endforelse
        </div>
        <div class="mt-5">{{ $items->links() }}</div>
    </x-learning-section>
@endsection
