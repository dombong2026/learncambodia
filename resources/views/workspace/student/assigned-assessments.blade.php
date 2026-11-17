@extends('layouts.workspace')
@section('title', 'Assigned Assessments')
@section('heading', 'Assigned Assessments')
@section('content')
<x-learning-manager-table :items="$exams" name="Assessment" measure="Questions" empty="You have no assigned exams or quizzes.">
    @foreach($exams as $exam)
        @php
            $enrollment = $enrollments->get($exam->course_id);
            $standalone = $exam->published && $exam->course_id === null;
            $href = $standalone ? $exam->publicUrl() : ($exam->published && $enrollment ? route('account.enrollments.show', $enrollment).'#course-exams' : null);
            $status = match (true) {
                !$exam->published => 'Awaiting activation',
                $href !== null => 'Available',
                default => 'Unavailable',
            };
        @endphp
        <x-learning-manager-row :number="$exams->firstItem() + $loop->index" :record="$exam"
            :category="$exam->course?->category?->name ?? $exam->type?->name" :level="$exam->managerLevel()"
            :measure="$exam->published ? count($exam->questions ?? []) : '—'" :status="$status" :href="$href" :secondary="ucfirst($exam->kind)">
            @if($href)<a href="{{ $href }}">{{ $standalone ? 'Preview' : 'Open course exams' }}</a>@endif
        </x-learning-manager-row>
    @endforeach
</x-learning-manager-table>
@endsection
