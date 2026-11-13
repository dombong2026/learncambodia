@extends('layouts.workspace')
@section('title','Teaching Insights')
@section('heading','Teaching Insights')
@section('description','Aggregate course activity only for your current approved teaching assignments. No student notes or private plans are exposed.')
@section('content')
<div class="mb-5 flex flex-wrap gap-3"><a href="{{ route('instructor.grading.index',['status'=>'submitted']) }}" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Review submitted work</a><a href="{{ route('instructor.homework.index') }}" class="rounded-xl border border-blue-500 px-5 py-3 text-sm font-bold text-blue-700 dark:text-blue-300">Homework Builder</a></div>
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
@forelse($courses as $course)
    @php($counts=$submissionsByCourse[$course->id]??[])
    <article class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-extrabold">{{ $course->title }}</h2>
        <p class="mt-1 text-xs text-slate-500">{{ $course->category?->name }}</p>
        <dl class="mt-5 grid grid-cols-2 gap-4">
            <div><dt class="text-xs text-slate-500">Approved enrollments</dt><dd class="mt-1 text-xl font-black">{{ $course->approved_enrollments_count }}</dd></div>
            <div><dt class="text-xs text-slate-500">Lesson completions</dt><dd class="mt-1 text-xl font-black">{{ $completed[$course->id]??0 }}</dd></div>
            <div><dt class="text-xs text-slate-500">Awaiting grading</dt><dd class="mt-1 text-xl font-black">{{ $counts['submitted']??0 }}</dd></div>
            <div><dt class="text-xs text-slate-500">Graded submissions</dt><dd class="mt-1 text-xl font-black">{{ $counts['graded']??0 }}</dd></div>
        </dl>
        <a href="{{ route('instructor.courses.show',$course) }}" class="mt-5 inline-block text-xs font-bold text-blue-700 dark:text-blue-300">Open assigned course</a>
    </article>
@empty<p class="text-sm text-slate-500">No active, published teaching assignments yet.</p>@endforelse
</div>
<div class="mt-6">{{ $courses->links() }}</div>
@endsection
