@extends('layouts.public')
@section('title', $title)
@section('meta_description', $lede)
@section('content')
@php
    // Only allowlisted information slugs arrive here; the artwork is decorative.
    $icon = [
        'instructors' => 'graduation',
        'blog' => 'book-open',
        'careers' => 'briefcase',
        'partners' => 'users',
        'placement' => 'target',
        'scores' => 'chart-line',
        'faq' => 'book-open',
        'terms' => 'file',
        'privacy' => 'shield-check',
    ][$page] ?? 'book-open';
    $isLegalDraft = in_array($page, ['terms', 'privacy'], true);
@endphp
<section data-information-page="{{ $page }}" class="information-page min-h-[60vh]">
    <header class="information-hero relative overflow-hidden bg-gradient-to-br from-[#0b2d59] via-[#123e71] to-[#0b2d59] text-white">
        <div class="pointer-events-none absolute -right-16 -top-20 h-72 w-72 rounded-full border-[48px] border-[#f0c575]/10" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-[1280px] px-4 py-12 sm:px-8 sm:py-16 lg:px-12 lg:py-20">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-lg text-sm font-semibold text-[#d8e6f5] transition hover:text-white focus-visible:outline-offset-4">
                <x-workspace-icon name="home" class="h-4 w-4" /> {{ __('longpage.home') }}
            </a>
            <div class="mt-8 grid items-end gap-7 md:grid-cols-[minmax(0,1fr)_96px]">
                <div class="min-w-0">
                    <p class="text-xs font-extrabold uppercase tracking-[.15em] text-[#f3cf82]">{{ __('ui.information') }}</p>
                    <h1 class="mt-3 max-w-4xl text-3xl font-black leading-tight tracking-tight sm:text-4xl lg:text-5xl">{{ $title }}</h1>
                    <p class="mt-5 max-w-3xl text-base leading-8 text-[#dfebf8] sm:text-lg">{{ $lede }}</p>
                </div>
                <div class="hidden h-24 w-24 items-center justify-center rounded-3xl border border-white/20 bg-white/10 text-[#f3cf82] md:flex" aria-hidden="true">
                    <x-workspace-icon :name="$icon" class="h-12 w-12" />
                </div>
            </div>
        </div>
    </header>
    <div class="mx-auto grid max-w-[1280px] gap-7 px-4 py-10 sm:px-8 sm:py-14 lg:grid-cols-[minmax(0,1fr)_280px] lg:px-12">
        <article class="information-surface min-w-0 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#122a49] sm:p-9">
            <h2 class="text-xl font-extrabold tracking-tight text-[#11345e] dark:text-white sm:text-2xl">{{ __('ui.page_overview') }}</h2>
            <p class="mt-5 whitespace-pre-line break-words text-base leading-9 text-slate-700 dark:text-slate-200">{{ $body }}</p>
            @if($isLegalDraft)
                <aside role="note" class="mt-8 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm font-semibold leading-7 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
                    <x-workspace-icon name="shield-check" class="mr-2 inline-block h-5 w-5 align-middle" />
                    {{ __('ui.draft_legal_notice') }}
                </aside>
            @endif
        </article>
        <aside class="information-surface h-fit rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#122a49]" aria-label="{{ __('ui.continue_learning') }}">
            <h2 class="text-base font-extrabold text-[#11345e] dark:text-white">{{ __('ui.continue_learning') }}</h2>
            <div class="mt-5 grid gap-3">
                <a href="{{ route('categories.index') }}" class="information-quick-link inline-flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-[#17477b] transition hover:border-blue-300 hover:bg-slate-50 dark:border-slate-700 dark:text-blue-200 dark:hover:bg-slate-800">
                    <x-workspace-icon name="book-open" class="h-5 w-5 shrink-0" /> {{ __('ui.explore_categories') }}
                </a>
                <a href="{{ route('cefr.index') }}" class="information-quick-link inline-flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-[#17477b] transition hover:border-blue-300 hover:bg-slate-50 dark:border-slate-700 dark:text-blue-200 dark:hover:bg-slate-800">
                    <x-workspace-icon name="graduation" class="h-5 w-5 shrink-0" /> {{ __('ui.view_cefr') }}
                </a>
            </div>
        </aside>
    </div>
</section>
@endsection
