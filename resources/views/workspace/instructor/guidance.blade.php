@extends('layouts.workspace')
@section('title', 'Academic Review Process')
@section('heading', 'Academic Review Process')
@section('description', 'Instructor guidance for course assignments and evaluations.')
@section('content')
<div class="max-w-4xl space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-800 dark:bg-slate-900"><h2 class="text-lg font-extrabold">1. Prepare a draft</h2><p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">Write clear learning outcomes, instructions, and transparent marking criteria. Save the draft in one of your authorized courses. Student coursework contains no external uploads at this milestone.</p></section>
    <section class="rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-800 dark:bg-slate-900"><h2 class="text-lg font-extrabold">2. Request academic approval</h2><p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">Submit a complete assignment for administrator review. It remains hidden from students until approval. Rejected assignments may be revised and resubmitted.</p></section>
    <section class="rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-800 dark:bg-slate-900"><h2 class="text-lg font-extrabold">3. Grade or return</h2><p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">Evaluate submitted work with a score from 0–100 and written feedback, or return it for revision. Students may resubmit up to three versions while the published deadline permits. Decisions are audited. No official certifications are awarded.</p></section>
    <div class="flex justify-end"><a href="{{ route('instructor.courses.index') }}" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white">Go to my courses</a></div>
</div>
@endsection
