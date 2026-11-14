<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <meta name="description" content="@yield('meta_description', 'Learn Cambodia — English learning, international exam preparation and teacher development.')">
    <title>@yield('title', 'Learn Cambodia') · Learn Cambodia</title>
    @include('partials.brand-head')
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
@php
    $typography = \App\Support\Appearance::approved('typography');
    $fontClass = ['system'=>'font-sans','serif'=>'font-serif','mono'=>'font-mono'][$typography['font_style']];
    $scaleClass = ['compact'=>'text-sm','normal'=>'text-base','large'=>'text-lg'][$typography['text_scale']];
@endphp
<body class="{{ $fontClass }} {{ $scaleClass }} min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:text-slate-900">{{ __('ui.skip_content') }}</a>
    @include('partials.header')
    <main id="main-content" class="min-h-[50vh]">@yield('content')</main>
    @include('partials.footer')
</body>
</html>
