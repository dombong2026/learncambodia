@extends('layouts.workspace')
@section('title', 'Social Auth')
@section('heading', 'Social Auth')

@push('workspace_styles')
    <link rel="stylesheet" href="{{ asset('css/social-auth-controls.css') }}?v=2">
    @if($selected)
        <script src="{{ asset('js/social-auth-dialog.js') }}?v=1" defer></script>
    @endif
@endpush

@section('content')
    <div class="max-w-5xl space-y-5">
        @if(!$selected && session('social_auth_error'))
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
                            <a id="social-auth-gear-{{ $provider }}" href="{{ route('admin.system-settings.social-auth.configure', $provider) }}" class="social-auth-gear" aria-haspopup="dialog" aria-label="Configure {{ $settings['label'] }}" title="Configure {{ $settings['label'] }}">
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
                $closeUrl = route('admin.system-settings.social-auth').'#social-auth-gear-'.$selected;
            @endphp
            <dialog id="social-auth-settings" data-social-auth-dialog open class="social-auth-dialog"
                aria-labelledby="social-config-heading" aria-describedby="social-config-status">
                <header class="social-auth-dialog-header">
                    <span class="social-auth-dialog-icon" aria-hidden="true"><x-workspace-icon name="gear" class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <h2 id="social-config-heading">{{ $settings['label'] }} OAuth settings</h2>
                        <p id="social-config-status" class="social-auth-dialog-meta">
                            {{ !$settings['configured'] ? 'Setup required' : ($settings['enabled'] ? 'Sign-in enabled' : 'Sign-in disabled') }}
                        </p>
                    </div>
                    <a data-social-auth-close href="{{ $closeUrl }}" class="social-auth-dialog-close" aria-label="Close settings" autofocus>
                        <x-workspace-icon name="close" class="h-5 w-5" />
                    </a>
                </header>

                <div class="social-auth-dialog-body">
                    @if(session('social_auth_error'))
                        <p role="alert" class="social-auth-dialog-message social-auth-dialog-error">{{ session('social_auth_error') }}</p>
                    @elseif(session('status'))
                        <p role="status" class="social-auth-dialog-message social-auth-dialog-success">{{ session('status') }}</p>
                    @endif

                    <div class="social-auth-callback">
                        <p class="social-auth-field-label">Callback URL</p>
                        <div class="social-auth-callback-value">
                            <code id="social-callback-url">{{ $settings['callback'] }}</code>
                            <button type="button" data-copy-secret="social-callback-url" class="social-auth-dialog-button" aria-label="Copy callback URL">Copy</button>
                        </div>
                        <p class="social-auth-dialog-meta">Register this URL in your {{ $settings['label'] }} app.</p>
                        @if($selected === 'facebook')
                            <a class="social-auth-data-deletion" href="{{ route('social.data-deletion') }}">Data deletion instructions</a>
                        @endif
                    </div>

                    <form id="social-auth-credentials" method="POST" action="{{ route('admin.system-settings.social-auth.save', $selected) }}" class="social-auth-credentials">
                        @csrf @method('PUT')
                        <label class="social-auth-field-label">Client ID
                            <input name="client_id" type="text" maxlength="512" value="{{ $settings['client_id'] }}" required autocomplete="off">
                        </label>
                        <label class="social-auth-field-label">Client secret
                            <input name="client_secret" type="password" maxlength="2048" autocomplete="new-password"
                                @required(!$settings['configured']) @if($settings['configured']) aria-describedby="social-auth-secret-help" @endif>
                        </label>
                        @if($settings['configured'])
                            <p id="social-auth-secret-help" class="social-auth-dialog-meta">Leave blank to keep the saved secret for this client ID.</p>
                        @endif
                    </form>
                    @if($settings['configured'])
                        <form id="social-auth-test" method="POST" action="{{ route('admin.system-settings.social-auth.test', $selected) }}">
                            @csrf
                        </form>
                    @endif
                </div>

                <footer class="social-auth-dialog-footer">
                    @if($settings['configured'])
                        <button type="submit" form="social-auth-test" class="social-auth-dialog-button" aria-label="Test {{ $settings['label'] }} sign-in">Test sign-in</button>
                    @endif
                    <button type="submit" form="social-auth-credentials" class="social-auth-dialog-button social-auth-dialog-primary">Save settings</button>
                </footer>
            </dialog>
        @endif

        <div class="flex flex-wrap gap-4">
            <a href="{{ route('security.methods') }}" class="text-sm font-semibold underline">Manage your connected accounts</a>
            <a href="{{ route('admin.appearance.edit', 'auth') }}" class="text-sm font-semibold underline">Edit sign-in and registration appearance</a>
        </div>
    </div>
@endsection
