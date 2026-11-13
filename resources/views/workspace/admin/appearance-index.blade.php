@extends('layouts.workspace')
@section('title','Appearance Management')
@section('heading','Appearance Management')
@section('description','Manage page-specific copy and global visual settings. Every public change is reviewed independently before activation.')
@section('content')
@php
    $pageSections = $sections->filter(fn($s)=>in_array($s['key'],['categories','single_category','author','about','contact'],true));
    $otherSections = $sections->reject(fn($s)=>in_array($s['key'],['categories','single_category','author','about','contact'],true));
    $pageIcons = ['categories'=>'tags','single_category'=>'book-open','author'=>'pen-square','about'=>'info','contact'=>'inbox'];
@endphp
<div class="rounded-2xl border border-[#e4d0a4] bg-[#fff8e9] px-6 py-5 text-sm leading-8 text-[#765524] dark:border-[#7e6437] dark:bg-[#413620] dark:text-[#f6d38e]">
    Pages share the existing global header/footer. This manager can edit safe text and presentation settings, but cannot inject HTML, scripts, external integrations, or publish content without a second administrator's approval.
</div>
<section class="mt-8">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><p class="text-xs font-extrabold uppercase tracking-[.14em] text-[#b17b26]">Frontend page layouts</p><h2 class="mt-2 text-2xl font-black text-[#11345c] dark:text-white">Reference-based long pages</h2></div>
        <a href="{{ route('admin.reviews.index',['focus'=>'appearance']) }}" class="rounded-xl border border-[#d5ac61] px-5 py-3 text-xs font-bold text-[#a37122] dark:text-[#f3c878]">Review queue</a>
    </div>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
        @foreach($pageSections as $section)
            @php
                $label=\App\Support\Appearance::LABELS[$section['key']] ?? $section['key'];
                $preview=\App\Support\Appearance::preview($section['key']);
            @endphp
            <article class="flex min-h-64 flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#d8af66] hover:shadow-lg dark:border-slate-700 dark:bg-[#172f4f]">
                <div class="flex items-start justify-between gap-3">
                    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-[#f5e5c0] text-[#af7a23] dark:bg-[#493c24]"><x-workspace-icon :name="$pageIcons[$section['key']]" class="h-7 w-7"/></span>
                    @if($section['pending'])<span class="rounded-full bg-amber-100 px-3 py-1 text-[11px] font-bold text-amber-800">Under review</span>
                    @elseif($section['approved'])<span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700">Active</span>
                    @else<span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-500">Default</span>@endif
                </div>
                <h3 class="mt-5 text-lg font-black text-[#11355f] dark:text-white">{{ $label }}</h3>
                <p class="mt-2 flex-1 text-xs leading-7 text-slate-500 dark:text-slate-300">{{ count(\App\Support\Appearance::SECTIONS[$section['key']]) }} editable fields · full-width continuous page</p>
                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.appearance.edit',$section['key']) }}" class="rounded-xl bg-[#123761] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#245187]">Customize</a>
                    @if($preview)
                        <a href="{{ $preview }}" target="_blank" rel="noopener" class="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-bold text-[#143a66] hover:border-[#d8a85c] dark:border-slate-600 dark:text-[#f3c875]">Preview</a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
<section class="mt-12">
    <h2 class="text-2xl font-black text-[#12345e] dark:text-white">Global & existing appearance settings</h2>
    <p class="mt-3 max-w-4xl text-sm leading-8 text-slate-500 dark:text-slate-300">Existing global Header, Menu, Footer, Typography and original Home, Course, Catalog and Authentication editing remain available.</p>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($otherSections as $section)
            @php $label=\App\Support\Appearance::LABELS[$section['key']] ?? ucwords(str_replace('_',' ',$section['key'])); @endphp
            <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#182f50]">
                <div class="flex items-start justify-between gap-2"><h3 class="font-black text-[#13365f] dark:text-white">{{ $label }}</h3>
                    <span class="text-[11px] font-bold text-slate-500">{{ $section['pending'] ? 'Under review' : ($section['approved'] ? 'Active' : 'Default') }}</span>
                </div>
                <p class="mt-2 flex-1 text-xs leading-7 text-slate-500">{{ count(\App\Support\Appearance::SECTIONS[$section['key']]) }} editable settings</p>
                <a href="{{ route('admin.appearance.edit',$section['key']) }}" class="mt-5 self-start rounded-xl border border-[#d8ae64] px-4 py-2.5 text-xs font-bold text-[#a77629] dark:text-[#f3c67b]">Manage settings</a>
            </div>
        @endforeach
    </div>
</section>
@endsection
