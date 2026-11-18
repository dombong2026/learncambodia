@extends('layouts.workspace')
@section('title', 'SMTP')
@section('heading', 'SMTP')
@section('description', 'Configure outgoing account email and send a test message.')

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
        @if($errors->any() && !$errors->has('recipient'))
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
                    <input name="from_address" type="email" required maxlength="255" value="{{ old('from_address', $settings['from_address']) }}" class="{{ $fieldClass }}">
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
        @error('recipient')
            <p role="alert" class="mt-4 rounded-xl border border-red-500/40 p-4 text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
        @enderror
        @if($canSend)
            <form method="POST" action="{{ route('admin.system-settings.smtp.test') }}" class="mt-5 space-y-4">
                @csrf
                <label class="block space-y-2 text-sm font-semibold">
                    <span class="block">Recipient email address</span>
                    <input name="recipient" type="email" required maxlength="255" value="{{ old('recipient', $email) }}" class="{{ $fieldClass }}">
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
