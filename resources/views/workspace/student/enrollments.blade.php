@extends('layouts.workspace')
@section('title', 'My Enrollments')
@section('heading', 'My Enrollments')
@section('heading_action')
    <a href="{{ route('account.saved-courses.index') }}" class="learning-manager-control">{{ __('saved_courses.title') }}</a>
    <a href="{{ route('account.courses.index') }}" class="learning-manager-control learning-manager-primary">Browse available courses</a>
@endsection
@section('content')
<x-learning-manager-table :items="$enrollments" name="Course" empty="No enrollment requests yet.">
    @foreach($enrollments as $enrollment)
        @php
            $course = $enrollment->course;
            $catalog = $course->status === 'published' && $course->published_at?->lte(now()) && $course->category?->is_published
                && !$course->superseded_at && !$course->deactivated_at;
            $lessons = $enrollment->status === 'approved' && $course->status === 'published' && $course->published_at?->lte(now())
                && $course->category?->is_published ? route('account.enrollments.show', $enrollment) : null;
        @endphp
        <x-learning-manager-row :number="$enrollments->firstItem() + $loop->index" :record="$course"
            :category="$course->category?->name" :level="$course->cefr_level ?? 'All'" :measure="$course->modules_count"
            :status="ucfirst($enrollment->status)" :href="$lessons">
            @if($lessons)<a href="{{ $lessons }}">Open lessons</a>@endif
            @if($catalog)<a href="{{ route('courses.show', $course) }}">Preview</a>@endif
            @if(in_array($enrollment->status, ['pending', 'approved'], true))
                <form method="post" action="{{ route('account.enrollments.cancel', $enrollment) }}" data-learning-delete="Cancel your enrollment in “{{ $course->title }}”?">
                    @csrf @method('DELETE')
                    <button class="learning-manager-delete" type="submit">Cancel enrollment</button>
                </form>
            @endif
        </x-learning-manager-row>
    @endforeach
</x-learning-manager-table>
@endsection
