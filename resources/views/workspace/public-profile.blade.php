@extends('layouts.workspace')
@section('title', __('people.manage_profile'))
@section('heading', __('people.manage_profile'))
@section('description', __('people.profile_editor_intro'))
@section('content')
@php $prefix=$role==='instructor' ? 'instructor' : 'account'; $editable=!$profile || in_array($profile->status,['draft','rejected','withdrawn'],true); @endphp
<div class="grid gap-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(260px,.75fr)]">
    <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-700 dark:bg-[#172f51]">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-black">{{ __('people.public_profile') }}</h2>
            <span class="rounded-full bg-[#fff1cd] px-3 py-1 text-xs font-extrabold text-[#78571e]">{{ $profile?->status ?? 'Not created' }}</span>
        </div>
        @if($role==='student' && !config('public_profiles.students_enabled'))
            <p class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-7 text-amber-900 dark:border-amber-900 dark:bg-[#47351f] dark:text-[#ffe0ad]">{{ __('people.student_privacy_gate') }}</p>
        @endif
        @if($editable)
        <form method="POST" action="{{ route($prefix.'.public-profile.save') }}" class="mt-6 grid gap-5">@csrf
            @foreach(['display_name'=>__('people.display_name'),'headline'=>__('people.headline'),'subjects'=>__('people.interests'),'city'=>__('people.city')] as $field=>$label)
                <div><label for="{{ $field }}" class="mb-1.5 block text-xs font-bold">{{ $label }}</label>
                    <input name="{{ $field }}" id="{{ $field }}" maxlength="{{ $field==='headline'||$field==='subjects' ? 160 : ($field==='city' ? 80 : 100) }}" value="{{ old($field,$profile?->{$field}) }}" @required(in_array($field,['display_name','headline'],true)) class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-[#102845]">
                    @error($field)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div><label for="bio" class="mb-1.5 block text-xs font-bold">{{ __('people.about') }}</label>
                <textarea name="bio" id="bio" maxlength="1500" rows="7" class="w-full rounded-xl border border-slate-300 bg-white p-4 text-sm leading-7 dark:border-slate-700 dark:bg-[#102845]">{{ old('bio',$profile?->bio) }}</textarea>
                @error('bio')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            @if($role==='student')
                <label class="flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-xs leading-6 text-[#684b22] dark:bg-[#4c3c23] dark:text-[#f4deb1]"><input type="checkbox" name="adult_confirmation" value="1" required class="mt-1"> {{ __('people.adult_confirmation') }}</label>
                <label class="flex items-start gap-3 text-xs leading-6"><input type="checkbox" name="show_learning_summary" value="1" @checked(old('show_learning_summary',$profile?->show_learning_summary)) class="mt-1"> {{ __('people.summary_consent') }}</label>
            @endif
            <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-xs leading-7 dark:bg-[#1b3c60]"><input type="checkbox" name="public_consent" value="1" required class="mt-1"> {{ __('people.public_consent') }}</label>
            <div class="flex justify-end"><button type="submit" class="rounded-xl bg-[#0d315e] px-6 py-3 text-sm font-black text-white hover:bg-[#1e4a80]">{{ __('people.save_private_draft') }}</button></div>
        </form>
        @else
            <p class="mt-5 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ __('people.locked_profile') }}</p>
        @endif
    </section>
    <aside class="space-y-5">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#172f51]">
            <h2 class="text-lg font-black">{{ __('people.publication_controls') }}</h2>
            <p class="mt-3 text-xs leading-7 text-slate-500 dark:text-slate-300">{{ __('people.publication_process') }}</p>
            @if($profile && $profile->status==='draft')
                <form method="POST" action="{{ route($prefix.'.public-profile.submit') }}" class="mt-5">@csrf
                    <button type="submit" class="w-full rounded-xl bg-[#0d315e] px-5 py-3 text-xs font-black text-white hover:bg-[#1e4a80]">{{ __('people.submit_review') }}</button>
                </form>
            @endif
            @if($profile && in_array($profile->status,['pending_review','published','rejected'],true))
                <form method="POST" action="{{ route($prefix.'.public-profile.withdraw') }}" class="mt-5">@csrf
                    <button type="submit" class="w-full rounded-xl border border-red-300 px-5 py-3 text-xs font-bold text-red-700 dark:text-red-300">{{ __('people.withdraw') }}</button>
                </form>
            @endif
            @if($profile && $profile->status==='published' && ($role==='instructor' || config('public_profiles.students_enabled')))
                <a href="{{ $role==='instructor' ? route('instructors.show',$profile) : route('students.show',$profile) }}" class="mt-4 block rounded-xl border border-slate-300 px-4 py-3 text-center text-xs font-bold dark:border-slate-700">{{ __('people.view_profile') }}</a>
            @endif
            @if($profile?->review_note)<p class="mt-4 rounded-xl bg-amber-50 p-4 text-xs leading-7 text-amber-900 dark:bg-[#49371e] dark:text-[#ffdead]">{{ $profile->review_note }}</p>@endif
        </div>
        <div class="rounded-3xl bg-[#0b2f5d] p-6 text-xs leading-7 text-[#dbe8fa]">
            <x-workspace-icon name="shield-check" class="h-7 w-7 text-[#f1c36d]" />
            <p class="mt-3">{{ __('people.editor_privacy_notice') }}</p>
            <a href="{{ route($prefix.'.profile.edit') }}" class="mt-4 inline-block font-bold text-[#ffde9b] underline">{{ __('people.profile_settings') }}</a>
        </div>
    </aside>
</div>
@endsection
