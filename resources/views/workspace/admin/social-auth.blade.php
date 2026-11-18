@extends('layouts.workspace')
@section('title', 'Social Auth')
@section('heading', 'Social Auth')

@push('workspace_styles')
    <link rel="stylesheet" href="{{ asset('css/social-auth-controls.css') }}?v=1">
@endpush

@section('content')
    <div class="max-w-5xl space-y-5">
        @if(session('social_auth_error'))
            <p role="alert" class="rounded-xl border border-red-300 px-4 py-3 text-sm">{{ session('social_auth_error') }}</p>
        @endif
        @error('google')<p role="alert" class="rounded-xl border border-red-300 px-4 py-3 text-sm">{{ $message }}</p>@enderror

        <section class="workspace-panel p-5 sm:p-6" aria-labelledby="social-providers">
            <h2 id="social-providers" class="mb-4 text-lg font-bold">Sign-in providers</h2>
            <div class="divide-y divide-slate-200 dark:divide-slate-700">
                @foreach($providers as $provider => $settings)
                    <div class="social-auth-provider-row">
                        <div class="min-w-0">
                            <h3 class="font-bold">{{ $settings['label'] }}</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                {{ $settings['configured'] ? $settings['source'] : 'Setup required' }}
                            </p>
                        </div>
                        <div class="social-auth-provider-actions">
                            <form method="POST" action="{{ route('admin.system-settings.social-auth.status', $provider) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="enabled" value="{{ $settings['enabled'] ? '0' : '1' }}">
                                @if($settings['configured'] && !$confirmed)
                                    <a class="social-auth-switch" data-enabled="{{ $settings['enabled'] ? 'true' : 'false' }}" href="{{ route('admin.system-settings.social-auth.confirm') }}" aria-label="Confirm password to change {{ $settings['label'] }} sign-in" title="Confirm password to change {{ $settings['label'] }} sign-in">
                                @else
                                    <button type="submit" class="social-auth-switch" data-enabled="{{ $settings['enabled'] ? 'true' : 'false' }}" role="switch" aria-checked="{{ $settings['enabled'] ? 'true' : 'false' }}" aria-label="{{ $settings['label'] }} sign-in" @disabled(!$settings['configured']) @if(!$settings['configured']) title="Configure {{ $settings['label'] }} before turning it on" @endif>
                                @endif
                                    <span class="social-auth-switch-thumb" aria-hidden="true"></span>
                                    <span aria-hidden="true">{{ $settings['enabled'] ? 'ON' : 'OFF' }}</span>
                                @if($settings['configured'] && !$confirmed)
                                    </a>
                                @else
                                    </button>
                                @endif
                            </form>
                            <a href="{{ route('admin.system-settings.social-auth.configure', $provider) }}" class="social-auth-gear" aria-label="Configure {{ $settings['label'] }}" title="Configure {{ $settings['label'] }}">
                                <x-workspace-icon name="gear" class="h-5 w-5" />
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @if($selected)
            @php
                $settings = $providers[$selected];
                $fieldClass = 'mt-1 block w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950';
            @endphp
            <section id="social-auth-settings" class="workspace-panel space-y-5 p-5 sm:p-6" aria-labelledby="social-config-heading">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="social-config-heading" class="text-lg font-bold">{{ $settings['label'] }} OAuth settings</h2>
                    <a href="{{ route('admin.system-settings.social-auth') }}" class="text-sm font-semibold underline">Close settings</a>
                </div>
                <div>
                    <p class="text-sm font-semibold">Callback URL</p>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <code id="social-callback-url" class="break-all text-sm">{{ $settings['callback'] }}</code>
                        <button type="button" data-copy-secret="social-callback-url" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">Copy callback URL</button>
                    </div>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Register this URL in your {{ $settings['label'] }} app.</p>
                    @if($selected === 'facebook')
                        <p class="mt-2 break-all text-sm">Data deletion instructions <a class="underline" href="{{ route('social.data-deletion') }}">{{ route('social.data-deletion') }}</a></p>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.system-settings.social-auth.save', $selected) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <label class="block text-sm font-semibold">Client ID
                        <input name="client_id" type="text" maxlength="512" value="{{ $settings['client_id'] }}" required autocomplete="off" class="{{ $fieldClass }}">
                    </label>
                    <label class="block text-sm font-semibold">Client secret
                        <input name="client_secret" type="password" maxlength="2048" autocomplete="new-password" @required(!$settings['configured']) class="{{ $fieldClass }}">
                        @if($settings['configured'])<span class="mt-1 block text-sm font-normal">Leave blank to keep the saved secret for this client ID.</span>@endif
                    </label>
                    <button type="submit" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Save settings</button>
                </form>
                @if($settings['configured'])
                    <form method="POST" action="{{ route('admin.system-settings.social-auth.test', $selected) }}" class="border-t border-slate-200 pt-4 dark:border-slate-700">
                        @csrf
                        <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">Test {{ $settings['label'] }} sign-in</button>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">The test uses saved settings and works while public sign-in is disabled.</p>
                    </form>
                @endif
            </section>
        @endif

        <div class="flex flex-wrap gap-4">
            <a href="{{ route('security.methods') }}" class="text-sm font-semibold underline">Manage your connected accounts</a>
            <a href="{{ route('admin.appearance.edit', 'auth') }}" class="text-sm font-semibold underline">Edit sign-in and registration appearance</a>
        </div>
    </div>
@endsection
