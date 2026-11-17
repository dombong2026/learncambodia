@extends('layouts.workspace')
@php
    $manager = match($kind) { 'quiz' => 'admin.quizzes-manager', 'exam' => 'admin.exams', default => 'admin.courses' };
    $course = $kind === 'course';
@endphp
@section('title', $record->title.' | Preview')
@section('heading', $record->title)
@section('description', ucfirst($kind).' preview · '.$record->managerStatus())
@section('heading_action')
    <a class="learning-manager-control" href="{{ route($manager.'.index') }}">Back to list</a>
@endsection
@section('content')
<div class="space-y-4" aria-label="Read-only preview">
    @if($record->featured_kind)
        <div class="workspace-panel p-4"><x-editorial-featured-media :entry="$record" /></div>
    @endif
    @if($course)
        <div class="workspace-panel space-y-3 p-5">
            <p class="text-sm font-semibold">{{ $record->category?->name }} · {{ $record->cefr_level ?? 'All levels' }}</p>
            <p class="leading-7">{{ $record->summary }}</p>
            @if($record->description_html || $record->description)
                <x-lesson-content :lesson="(object) ['content_html' => $record->description_html, 'content' => $record->description]" />
            @endif
        </div>
        @forelse($record->modules as $module)
            <section class="workspace-panel p-5">
                <h2 class="font-bold">{{ $loop->iteration }}. {{ $module->title }}</h2>
                <ul class="mt-3 space-y-2">
                    @foreach($module->lessons as $lesson)
                        <li class="flex justify-between gap-4 text-sm">{{ $lesson->title }} <span class="shrink-0">{{ $lesson->duration_minutes }} min</span></li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="workspace-panel p-5">No modules have been added.</p>
        @endforelse
    @else
        <div class="workspace-panel space-y-3 p-5">
            <p class="text-sm font-semibold">{{ $record->type?->name }} · {{ $record->time_limit }} min · {{ $record->passing_percent }}% passing score</p>
            @if($record->instructions_html)
                <x-lesson-content :lesson="(object) ['content_html' => $record->instructions_html, 'content' => '']" />
            @endif
        </div>
        @forelse($record->questions ?? [] as $question)
            <section class="workspace-panel space-y-3 p-5">
                <h2 class="font-bold">{{ $loop->iteration }}. {{ $question['prompt'] }}</h2>
                @if(!empty($question['passage']))<p class="whitespace-pre-wrap text-sm">{{ $question['passage'] }}</p>@endif
                @if(!empty($question['media_id']))<audio controls preload="none" src="{{ route('editor-media.show', $question['media_id']) }}"></audio>@endif
                <ul class="space-y-2">
                    @foreach(array_intersect_key($question, array_flip(['a', 'b', 'c', 'd'])) as $option => $text)
                        @if(filled($text))<li class="text-sm">{{ strtoupper($option) }}. {{ $text }}</li>@endif
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="workspace-panel p-5">No questions have been added.</p>
        @endforelse
    @endif
</div>
@endsection
