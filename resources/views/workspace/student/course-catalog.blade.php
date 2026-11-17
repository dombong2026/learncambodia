@extends('layouts.workspace')
@section('title', 'Available Courses')
@section('heading', 'Available Courses')
@section('heading_action')
    <a href="{{ route('account.saved-courses.index') }}" class="learning-manager-control">{{ __('saved_courses.title') }}</a>
@endsection
@section('content')
<x-learning-manager-table :items="$courses" name="Course" empty="No courses are available at the moment.">
    <x-slot:empty-state>
        <x-workspace-empty-state :title="__('ui.empty_courses')" description="No courses have passed academic review." icon="graduation" :href="route('categories.index')" :action="__('ui.explore_categories')" />
    </x-slot:empty-state>
    @foreach($courses as $course)
        @php
            $page = route('courses.show', $course);
            $saved = in_array($course->id, $savedCourseIds, true);
            $enrollment = $enrollments->get($course->id);
            $quote = \App\Support\CoursePricing::quote($course->pricing);
            $price = match ($quote['model']) {
                'paid' => \App\Support\CoursePricing::formatAmount($quote['amount_minor'], $quote['currency']),
                'membership' => $quote['plan'].' membership',
                default => 'Free',
            };
        @endphp
        <x-learning-manager-row :number="$courses->firstItem() + $loop->index" :record="$course"
            :category="$course->category?->name" :level="$course->cefr_level ?? 'All'" :measure="$course->modules_count"
            :status="$enrollment ? ucfirst($enrollment->status) : 'Available'" :href="$page" :secondary="$price">
            <a href="{{ $page }}">Preview</a>
            @if($enrollment?->status === 'approved')
                <a href="{{ route('account.enrollments.show', $enrollment) }}">Open lessons</a>
            @elseif(!$enrollment)
                <form method="post" action="{{ route('account.enrollments.store', $course) }}">
                    @csrf
                    @if($quote['model'] !== 'free')<input type="hidden" name="pricing_quote" value="{{ json_encode($quote, JSON_THROW_ON_ERROR) }}">@endif
                    <button type="submit">Request enrollment</button>
                </form>
            @endif
            <form method="post" action="{{ route($saved ? 'account.saved-courses.destroy' : 'account.saved-courses.store', $course) }}">
                @csrf
                @if($saved)@method('DELETE')@endif
                <button type="submit" aria-label="{{ __($saved ? 'saved_courses.remove_course' : 'saved_courses.save_course', ['course' => $course->title]) }}">
                    {{ __($saved ? 'saved_courses.remove' : 'saved_courses.save') }}
                </button>
            </form>
        </x-learning-manager-row>
    @endforeach
</x-learning-manager-table>
@endsection
