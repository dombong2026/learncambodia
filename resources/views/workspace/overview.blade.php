@extends('layouts.workspace')
@section('title', $heading)
@section('heading', $heading)
@section('description', $description)
@section('content')
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach($stats as $stat)
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
            <p class="mt-4 text-3xl font-extrabold tabular-nums">{{ number_format($stat['value']) }}</p>
        </div>
    @endforeach
</div>
<div class="mt-8 grid gap-5 xl:grid-cols-2">
    <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-extrabold">{{ $role === 'developer' ? 'Developer tools' : ($role === 'admin' ? 'School management' : ($role === 'instructor' ? 'Teaching tools' : 'Discover learning paths')) }}</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">
            {{ $role === 'developer' ? 'Read API documentation and create scoped, expiring personal access tokens. Production write APIs are not enabled.' : ($role === 'admin' ? 'Review categories, registered users and course records. Additional school workflows remain under development.' : ($role === 'instructor' ? 'Prepare homework in assigned courses, request academic approval, and grade eligible student submissions.' : 'Explore published course categories and compare CEFR proficiency descriptors before selecting a study plan.')) }}
        </p>
        <div class="mt-6 flex flex-wrap gap-2">
        @if($role === 'admin')
            <a href="{{ route('admin.categories.index') }}" class="rounded-full bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Manage categories</a>
            <a href="{{ route('admin.learning.report') }}" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">Learning reports</a>
            <a href="{{ route('admin.instructors.index') }}" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">Instructors</a>
        @elseif($role === 'developer')
            <a href="{{ route('developer.tokens.index') }}" class="rounded-full bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Manage tokens</a>
            <a href="{{ route('developer.apis') }}" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">API reference</a>
        @elseif($role === 'instructor')
            <a href="{{ route('instructor.courses.index') }}" class="rounded-full bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white">My assigned courses</a>
            <a href="{{ route('instructor.grading.index') }}" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">Grade student work</a>
        @else
            <a href="{{ route('account.progress.index') }}" class="rounded-full bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">My learning progress</a>
            <a href="{{ route('account.practice.index') }}" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">Practice quizzes</a>
            <a href="{{ route('account.homework.index') }}" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">My assignments</a>
        @endif
        </div>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-extrabold">Account protection</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">Your workspace is protected by server-side role checks. The account menu contains your profile and password settings. Other roles' workspaces are not accessible to this account.</p>
        <a href="{{ route($role === 'admin' ? 'admin.profile.edit' : ($role === 'developer' ? 'developer.profile.edit' : ($role === 'instructor' ? 'instructor.profile.edit' : 'account.profile.edit'))) }}" class="mt-6 inline-flex rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold hover:border-blue-500 dark:border-slate-700">Profile &amp; security</a>
    </div>
</div>
@endsection
