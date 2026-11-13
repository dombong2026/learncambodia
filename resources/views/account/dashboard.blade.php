@extends('layouts.public')
@section('title', 'My Learning Account')
@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><p class="text-sm font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Student workspace</p><h1 class="mt-2 text-3xl font-extrabold">Welcome, {{ auth()->user()->name }}</h1><p class="mt-3 text-sm text-slate-600 dark:text-slate-400">Your account is ready to explore learning pathways.</p></div>
        <form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">Sign out</button></form>
    </div>
    <div class="mt-8 grid gap-5 sm:grid-cols-2">
        <a href="{{ route('categories.index') }}" class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm hover:border-blue-500 dark:border-slate-800 dark:bg-slate-900"><h2 class="text-xl font-bold">Explore learning pathways</h2><p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">Browse approved category descriptions covering English, assessments and teaching.</p><span class="mt-6 inline-block text-sm font-semibold text-blue-700 dark:text-blue-400">View categories →</span></a>
        <a href="{{ route('cefr.index') }}" class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm hover:border-blue-500 dark:border-slate-800 dark:bg-slate-900"><h2 class="text-xl font-bold">Understand CEFR levels</h2><p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">Read A1 to C2 learning descriptors and plan study goals.</p><span class="mt-6 inline-block text-sm font-semibold text-blue-700 dark:text-blue-400">View level guide →</span></a>
    </div>
</section>
@endsection
