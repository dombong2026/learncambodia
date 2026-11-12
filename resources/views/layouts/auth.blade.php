<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>@yield('title') · Learn Cambodia</title>
    @vite(['resources/css/app.css','resources/js/app.ts'])
</head>
@php
    $typography = \App\Support\Appearance::approved('typography');
    $fontClass = ['system'=>'font-sans','serif'=>'font-serif','mono'=>'font-mono'][$typography['font_style']];
@endphp
<body class="{{ $fontClass }} min-h-screen bg-gradient-to-br from-slate-100 via-blue-50 to-slate-100 text-slate-900 antialiased dark:from-slate-950 dark:via-blue-950 dark:to-slate-950 dark:text-white">
    <main class="grid min-h-screen place-items-center px-4 py-16">
        <div class="w-full max-w-md rounded-3xl border border-white/75 bg-white p-7 shadow-2xl shadow-slate-900/10 sm:p-9 dark:border-slate-800 dark:bg-slate-900">
            <a href="{{ route('home') }}" class="mb-7 flex items-center justify-center gap-2 text-xl font-black tracking-tight"><span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-700 text-white">L</span><span>Learn<span class="text-blue-700 dark:text-blue-400">Cambodia</span></span></a>
            @yield('content')
        </div>
    </main>
    <a href="{{ route('home') }}" aria-label="Return home" class="fixed bottom-5 left-5 flex items-center gap-2 rounded-full px-3 py-2 text-sm font-semibold text-slate-600 transition hover:text-blue-700 dark:text-slate-300 dark:hover:text-blue-300">
        <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-7 9 7v10H3V10Z"/><path d="M9 20v-7h6v7"/></svg><span>Home</span>
    </a>
</body>
</html>
