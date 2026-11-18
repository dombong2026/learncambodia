@extends('layouts.workspace')
@section('title', 'SMTP')
@section('heading', 'SMTP')

@section('workspace_feedback')
    @php
        $testRecipient = session('smtp_test_recipient');
        $testError = session('smtp_test_error');
    @endphp
    @if($testRecipient || $testError)
        <aside id="smtp-test-feedback" data-smtp-test-feedback class="fixed right-4 top-20 z-[80] max-w-sm rounded-2xl border bg-white px-5 py-3 text-sm shadow-xl dark:bg-slate-900 {{ $testError ? 'border-red-500/40 text-red-700 dark:text-red-300' : 'border-emerald-200 text-emerald-800 dark:border-emerald-900 dark:text-emerald-300' }}">
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1 break-words" role="{{ $testError ? 'alert' : 'status' }}">
                    <p class="font-bold">{{ $testError ? 'Test email failed' : 'Mail server accepted the test email' }}</p>
                    @if($testError)
                        <p id="smtp-test-error" class="mt-1">{{ $testError }}</p>
                    @else
                        <p class="mt-1">Check {{ $testRecipient }} for “LearnCambodia SMTP test”, including the spam folder.</p>
                    @endif
                </div>
                <button id="smtp-test-feedback-close" type="button" aria-label="Dismiss test email result" class="rounded-lg p-1.5 focus-visible:outline-2">
                    <x-workspace-icon name="close" class="h-4 w-4" />
                </button>
            </div>
        </aside>
        <script>
            document.getElementById('smtp-test-feedback-close').addEventListener('click', () => {
                const previousControl = document.activeElement;
                document.getElementById('smtp-test-feedback').remove();
                if (previousControl?.id === 'smtp-test-feedback-close') {
                    document.getElementById('smtp-test-recipient')?.focus();
                }
            });
        </script>
    @endif
@endsection

@section('content')
@if(!$canManage)
    <section class="workspace-panel max-w-xl p-5 sm:p-6" aria-labelledby="smtp-unlock-heading">
        <h2 id="smtp-unlock-heading" class="text-lg font-bold">Confirm your password</h2>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Confirm your account password to view the SMTP settings, make changes, and send a test email.</p>
        <a href="{{ route('admin.system-settings.smtp.configure') }}" class="mt-5 inline-flex rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white hover:bg-blue-800">Confirm password</a>
    </section>
@else
@php
    $fieldClass = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';
@endphp
<div class="grid items-start gap-5 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
    <section class="workspace-panel p-5 sm:p-6" aria-labelledby="smtp-heading">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <h2 id="smtp-heading" class="text-lg font-bold">SMTP connection</h2>
            <span class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 dark:bg-blue-950 dark:text-blue-200">{{ $source }}</span>
        </div>
        @if($errors->any())
            <ul role="alert" class="mb-5 list-inside list-disc rounded-xl border border-red-500/40 p-4 text-sm text-red-700 dark:text-red-300">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif
        <form method="POST" action="{{ route('admin.system-settings.smtp.update') }}" class="space-y-5">
            @csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="space-y-2 text-sm font-semibold">
                    <span class="block">SMTP host</span>
                    <input name="host" required maxlength="253" value="{{ old('host', $settings['host']) }}" autocomplete="off" placeholder="smtp.example.com" class="{{ $fieldClass }}">
                </label>
                <label class="space-y-2 text-sm font-semibold">
                    <span class="block">Port</span>
                    <input name="port" type="number" min="1" max="65535" required value="{{ old('port', $settings['port']) }}" class="{{ $fieldClass }}">
                </label>
                <label class="space-y-2 text-sm font-semibold sm:col-span-2">
                    <span class="block">Encryption</span>
                    <select name="scheme" required class="{{ $fieldClass }}">
                        <option value="smtp" @selected(old('scheme', $settings['scheme']) === 'smtp')>STARTTLS</option>
                        <option value="smtps" @selected(old('scheme', $settings['scheme']) === 'smtps')>Implicit TLS (SMTPS)</option>
                    </select>
                </label>
                <label class="space-y-2 text-sm font-semibold">
                    <span class="block">SMTP username</span>
                    <input name="username" maxlength="255" value="{{ old('username', $settings['username']) }}" autocomplete="off" class="{{ $fieldClass }}">
                </label>
                <label class="space-y-2 text-sm font-semibold">
                    <span class="block">SMTP password</span>
                    <input name="password" type="password" maxlength="1024" autocomplete="new-password" aria-describedby="smtp-password-note" class="{{ $fieldClass }}">
                    <span id="smtp-password-note" class="block text-xs font-normal text-slate-600 dark:text-slate-400">{{ $hasPassword ? 'Leave blank to keep the current password for this connection.' : 'Use an app password if your provider requires one.' }}</span>
                </label>
                <label class="space-y-2 text-sm font-semibold">
                    <span class="block">From name</span>
                    <input name="from_name" required maxlength="120" value="{{ old('from_name', $settings['from_name']) }}" class="{{ $fieldClass }}">
                </label>
                <label class="space-y-2 text-sm font-semibold">
                    <span class="block">From email address</span>
                    <input name="from_address" type="email" required maxlength="255" value="{{ old('from_address', $settings['from_address']) }}" class="{{ $fieldClass }}" @if($gmailSenderMismatch) aria-describedby="smtp-gmail-sender-note" @endif>
                    @if($gmailSenderMismatch)
                        <span id="smtp-gmail-sender-note" class="block text-xs font-normal text-slate-600 dark:text-slate-400">For a Gmail test, use the SMTP username as the From address or use an address verified in Gmail’s Send mail as settings.</span>
                    @endif
                </label>
            </div>
            <button type="submit" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white hover:bg-blue-800">Save SMTP settings</button>
        </form>
        @if($dashboardSaved)
            <form method="POST" action="{{ route('admin.system-settings.smtp.reset') }}" class="mt-4">
                @csrf @method('DELETE')
                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">Restore server mail configuration</button>
            </form>
        @endif
    </section>

    <section class="workspace-panel p-5 sm:p-6" aria-labelledby="smtp-test-heading">
        <h2 id="smtp-test-heading" class="text-lg font-bold">Send a test email</h2>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">The test uses the active mail settings. Send it to an inbox you can check.</p>
        @if($canSend)
            <form method="POST" action="{{ route('admin.system-settings.smtp.test') }}" class="mt-5 space-y-4">
                @csrf
                <label class="block space-y-2 text-sm font-semibold">
                    <span class="block">Recipient email address</span>
                    <input id="smtp-test-recipient" name="recipient" type="email" required maxlength="255" value="{{ old('recipient', $email) }}" class="{{ $fieldClass }}">
                </label>
                <button type="submit" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white hover:bg-blue-800">Send test email</button>
            </form>
        @else
            <p class="mt-4 text-sm font-semibold">Save SMTP settings to enable a test email.</p>
        @endif
    </section>
</div>
@endif
@endsection
