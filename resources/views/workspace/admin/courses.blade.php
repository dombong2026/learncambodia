@extends('layouts.workspace')
@section('title', 'Course Manager')
@section('heading', 'Course Manager')
@section('content')
<x-learning-manager-table :items="$courses" name="Course" empty="No courses have been created.">
    <x-slot:empty-state><x-workspace-empty-state title="No courses yet." icon="book-open" /></x-slot:empty-state>
    @foreach($courses as $course)
        @php
            $edit = route('admin.course-builder.edit', $course);
            $active = match (true) {
                $course->superseded_at !== null => null,
                $course->isMutable() => false,
                $course->status === 'published' => $course->deactivated_at === null,
                default => null,
            };
        @endphp
        <x-learning-manager-row :number="$courses->firstItem() + $loop->index" :record="$course"
            :category="$course->category?->name" :level="$course->cefr_level ?? 'All'" :measure="$course->modules_count"
            :status="$course->managerStatus()" :href="$edit">
            @include('workspace.admin.learning-record-actions', ['record' => $course, 'manager' => 'admin.courses', 'edit' => $edit, 'active' => $active])
        </x-learning-manager-row>
    @endforeach
</x-learning-manager-table>
@endsection
