@extends('layouts.workspace')
@section('title', 'Student Help')
@section('heading', 'Student Help Center')
@section('description', 'Guidance for starting your learning journey.')
@section('content')
<div class="max-w-3xl rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-xl font-bold">How to get started</h2>
    <p class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-400">Explore the English categories, review CEFR level descriptors and check the public study pathways. Enrollments, placement tests and ticketing will be available after their educational and privacy reviews. Live chat is not enabled.</p>
    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('account.categories') }}" class="rounded-full bg-blue-700 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-800">Browse categories</a>
        <a href="{{ route('account.cefr') }}" class="rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold dark:border-slate-700">CEFR guide</a>
    </div>
</div>
@endsection
