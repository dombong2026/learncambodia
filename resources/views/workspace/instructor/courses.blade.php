@extends('layouts.workspace')
@section('title', 'My Assigned Courses')
@section('heading', 'My Assigned Courses')
@section('content')
<div class="mb-5 flex flex-wrap gap-2">
    <a href="{{ route('instructor.insights.index') }}" class="learning-manager-control">Teaching insights</a>
    <a href="{{ route('instructor.feedback-templates.index') }}" class="learning-manager-control">Feedback templates</a>
</div>
<x-learning-manager-table :items="$courses" name="Course" empty="No active teaching assignments are available yet.">
    @foreach($courses as $course)
        @php($teaching = route('instructor.courses.show', $course))
        <x-learning-manager-row :number="$courses->firstItem() + $loop->index" :record="$course"
            :category="$course->category?->name" :level="$course->cefr_level ?? 'All'" :measure="$course->modules_count"
            :status="$course->managerStatus()" :href="$teaching">
            <a href="{{ $teaching }}">Open teaching workspace</a>
            @if(!$course->deactivated_at && !$course->superseded_at)<a href="{{ route('courses.show', $course) }}">Preview</a>@endif
        </x-learning-manager-row>
    @endforeach
</x-learning-manager-table>
@endsection
