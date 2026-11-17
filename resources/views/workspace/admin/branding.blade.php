@extends('layouts.workspace')
@section('title', __('Branding'))
@section('heading', __('Branding'))
@section('description', __('Manage the platform name, logos, icons, and default metadata.'))

@push('workspace_styles')
    <link rel="stylesheet" href="{{ asset('css/branding-settings.css') }}?v=1">
@endpush

@section('heading_action')
    <button type="submit" form="platform-branding-form" class="inline-flex items-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
        {{ __('Save changes') }}
    </button>
@endsection

@section('content')
    @php
        $field = 'w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';
        $panel = 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-700 dark:bg-slate-900';
    @endphp
    <form id="platform-branding-form" action="{{ route('admin.system-settings.branding.update', request()->only('lang')) }}" method="post" enctype="multipart/form-data" class="branding-settings max-w-5xl space-y-5">
        @csrf
        @method('PUT')
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-700 dark:bg-red-950 dark:text-red-200">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
                <p class="mt-2">{{ __('Choose any replacement images again before saving.') }}</p>
            </div>
        @endif

        <section class="system-settings-row {{ $panel }}" aria-labelledby="branding-identity">
            <h2 id="branding-identity" class="text-lg font-bold">{{ __('Platform identity') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="space-y-1.5">
                    <span class="block text-sm font-semibold">{{ __('Platform name') }}</span>
                    <input class="{{ $field }}" name="name" value="{{ old('name', \App\Support\PlatformBranding::name()) }}" maxlength="80" required>
                </label>
                <label class="space-y-1.5">
                    <span class="block text-sm font-semibold">{{ __('Short name') }}</span>
                    <input class="{{ $field }}" name="short_name" value="{{ old('short_name', \App\Support\PlatformBranding::shortName()) }}" maxlength="32" required aria-describedby="branding-short-help">
                    <span id="branding-short-help" class="block text-xs text-slate-600 dark:text-slate-300">{{ __('Used as the installed app’s short name.') }}</span>
                </label>
            </div>
        </section>

        <section class="{{ $panel }}" aria-labelledby="branding-assets">
            <h2 id="branding-assets" class="text-lg font-bold">{{ __('Logos and icons') }}</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('Upload each asset as a PNG up to 2 MB. Images you leave blank keep their current files.') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach(['logo-light', 'logo-dark'] as $slot)
                    @include('workspace.admin.branding-asset', ['slot' => $slot])
                @endforeach
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach(['favicon-16', 'favicon-32', 'touch-icon', 'app-icon-192', 'app-icon-512'] as $slot)
                    @include('workspace.admin.branding-asset', ['slot' => $slot])
                @endforeach
            </div>
        </section>

        <section class="{{ $panel }}" aria-labelledby="branding-metadata">
            <h2 id="branding-metadata" class="text-lg font-bold">{{ __('Tagline and default metadata') }}</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('The tagline appears in the public footer. The meta title and description apply to the homepage and fill missing metadata on other public pages.') }}</p>
            <div class="mt-4 space-y-3">
                @foreach($locales as $locale => $label)
                    <details class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700" @if($locale === app()->getLocale() || $errors->has("translations.$locale.*")) open @endif>
                        <summary class="cursor-pointer px-4 py-3 text-sm font-bold">{{ $label }}</summary>
                        <div class="grid gap-4 px-4 pb-4">
                            @foreach(['tagline' => ['Tagline', 120], 'meta_title' => ['Meta title', 80], 'meta_description' => ['Meta description', 200]] as $key => [$caption, $limit])
                                @php
                                    $value = old("translations.$locale.$key", \App\Support\PlatformBranding::text($key, $locale));
                                    $name = "translations[$locale][$key]";
                                @endphp
                                <label class="space-y-1.5">
                                    <span class="block text-sm font-semibold">{{ __($caption) }}</span>
                                    @if($key === 'meta_description')
                                        <textarea class="{{ $field }}" name="{{ $name }}" rows="3" maxlength="{{ $limit }}">{{ $value }}</textarea>
                                    @else
                                        <input class="{{ $field }}" name="{{ $name }}" value="{{ $value }}" maxlength="{{ $limit }}">
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        </section>

        <section class="{{ $panel }}" aria-labelledby="branding-sharing">
            <h2 id="branding-sharing" class="text-lg font-bold">{{ __('Social sharing') }}</h2>
            <p class="mb-4 mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('Used when a public page has no sharing image of its own.') }}</p>
            @include('workspace.admin.branding-asset', ['slot' => 'share-image'])
        </section>
    </form>
@endsection
