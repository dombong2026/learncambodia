@extends('layouts.workspace')
@section('title', 'Appearance Management')
@section('heading', 'Appearance Management')
@section('description', 'Customize approved page headings and section copy. Each change is a private draft until an independent administrator approves publication.')
@section('content')
<div class="mb-5 rounded-2xl border border-[#e9d5a8] bg-[#fff8e9] p-5 text-sm leading-7 text-[#6d4a14] dark:border-[#776039] dark:bg-[#40351f] dark:text-[#f7d898]">
    Page appearance edits update published text only after a second-administrator review. The shared header/footer structure remains fixed; no custom HTML, CSS, scripts, or unsupported integrations can be enabled here.
</div>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach($sections as $section)
        <a href="{{ route('admin.appearance.edit', $section['key']) }}" class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-400 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-extrabold capitalize text-[#0e315e] group-hover:text-[#b17a22] dark:text-white">{{ \App\Support\Appearance::LABELS[$section['key']] ?? ucwords(str_replace('_',' ',$section['key'])) }}</h2>
                @if($section['pending'])<span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300">Under review</span>
                @elseif($section['approved'])<span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Active</span>
                @else<span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-500 dark:bg-slate-800">Default</span>@endif
            </div>
            <p class="mt-4 text-sm leading-7 text-slate-500 dark:text-slate-400">Manage reviewed headings, introductions and section labels for this page or shared component.</p>
            <p class="mt-4 text-xs font-bold text-[#9f7627]">{{ count(\App\Support\Appearance::SECTIONS[$section['key']]) }} editable settings</p>
            <span class="mt-6 inline-block text-sm font-bold text-[#b17a22]">Edit page appearance →</span>
        </a>
    @endforeach
</div>
<div class="mt-8 flex justify-end">
    <a href="{{ route('admin.reviews.index', ['focus' => 'appearance']) }}" class="rounded-full border border-slate-300 px-6 py-3 text-sm font-semibold hover:border-blue-400 dark:border-slate-700">Open appearance review queue</a>
</div>
@endsection
