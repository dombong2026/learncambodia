@extends('layouts.workspace')
@section('title', __('course_support.settings'))
@section('heading', __('course_support.settings'))
@section('description', $course->title)

@section('content')
    <div class="flex flex-wrap gap-3">
        <a class="learning-manager-control" href="{{ route('instructor.courses.show', [$course, 'lang' => app()->getLocale()]) }}">{{ __('course_support.back_course') }}</a>
        <a class="learning-manager-control" href="{{ route('instructor.lesson-support.index', ['lang' => app()->getLocale()]) }}">{{ __('course_support.inbox') }}</a>
    </div>
    <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-7" aria-labelledby="course-support-settings">
        <h2 id="course-support-settings" class="text-xl font-extrabold">{{ __('course_support.external_channels') }}</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ __('course_support.settings_intro') }}</p>

        <form method="POST" action="{{ route('instructor.courses.support.update', [$course, 'lang' => app()->getLocale()]) }}" class="mt-5 space-y-5">
            @csrf
            @method('PATCH')
            <div class="grid gap-4 md:grid-cols-2">
                @foreach($examples as $platform => $example)
                    <fieldset class="min-w-0 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <legend class="px-1 font-bold">{{ $platform }}</legend>
                        <input type="hidden" name="channels[{{ $platform }}][enabled]" value="0">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold">
                            <input type="checkbox" name="channels[{{ $platform }}][enabled]" value="1"
                                @checked(old("channels.$platform.enabled", $settings[$platform]['enabled'] ?? false))>
                            {{ __('course_support.show_channel') }}
                        </label>
                        <label class="mt-3 block text-sm font-semibold" for="channel-{{ $platform }}">{{ __('course_support.contact_link') }}</label>
                        <input id="channel-{{ $platform }}" name="channels[{{ $platform }}][url]" type="url" maxlength="255" autocomplete="off"
                            value="{{ old("channels.$platform.url", $settings[$platform]['url'] ?? '') }}" placeholder="{{ $example }}"
                            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        @error("channels.$platform.url")<p class="mt-2 text-sm text-red-700 dark:text-red-300" role="alert">{{ $message }}</p>@enderror
                    </fieldset>
                @endforeach
            </div>
            <button class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white hover:bg-blue-800" type="submit">{{ __('course_support.save') }}</button>
        </form>
    </section>
@endsection
