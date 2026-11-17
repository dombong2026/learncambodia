@extends('layouts.workspace')
@php
    $manager = $kind === 'quiz' ? 'admin.quizzes-manager' : 'admin.exams';
    $singular = ucfirst($kind);
    $plural = $kind === 'quiz' ? 'Quizzes' : 'Exams';
@endphp
@section('title', $plural)
@section('heading', $plural)
@section('heading_action')
    <a class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white" href="{{ route($manager.'.create') }}">Create {{ $singular }}</a>
@endsection
@section('content')
<x-learning-manager-table :items="$exams" :name="$singular" measure="Questions" :empty="'No '.strtolower($plural).' have been created.'">
    @foreach($exams as $exam)
        @php($edit = route($manager.'.edit', $exam))
        <x-learning-manager-row :number="$exams->firstItem() + $loop->index" :record="$exam"
            :category="$exam->course?->category?->name ?? $exam->type?->name" :level="$exam->managerLevel()"
            :measure="count($exam->questions ?? [])" :status="$exam->managerStatus()" :href="$edit" :secondary="$exam->course?->title">
            @include('workspace.admin.learning-record-actions', ['record' => $exam, 'edit' => $edit, 'active' => $exam->published])
        </x-learning-manager-row>
    @endforeach
</x-learning-manager-table>
@endsection
