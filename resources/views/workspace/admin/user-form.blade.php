@extends('layouts.workspace')
@php
    $editing = $account->exists;
    $heading = $editing ? 'Edit User' : ($sendInvitation ? __('invitations.send') : 'Add User');
    $fieldClass = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';
@endphp
@section('title', $heading)
@section('heading', $heading)
@section('description', $editing ? $account->name.' · '.$account->email : 'Create the account and choose its workspace access.')
@section('heading_action')
    <a href="{{ $editing ? route('admin.users.show', $account) : route('admin.users.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">Cancel</a>
@endsection
@push('workspace_styles')
    <script src="{{ asset('js/admin-user-access.js') }}?v=1" defer></script>
@endpush

@section('content')
@unless($editing)
    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Account creation method">
        <a href="{{ route('admin.users.create', ['method' => 'invite']) }}" @if($sendInvitation) aria-current="page" @endif class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold dark:border-slate-700">{{ __('invitations.send') }}</a>
        <a href="{{ route('admin.users.create') }}" @unless($sendInvitation) aria-current="page" @endunless class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold dark:border-slate-700">{{ __('invitations.manual') }}</a>
    </nav>
@endunless
@if($sendInvitation && !$invitationDeliveryEnabled)
    <p role="status" class="workspace-panel p-5 text-sm">{{ __('invitations.delivery_unavailable') }}</p>
@else
<form action="{{ $editing ? route('admin.users.access.update', $account) : route('admin.users.store') }}" method="POST" class="max-w-4xl space-y-5" data-admin-user-form>
    @csrf
    @if($sendInvitation)<input type="hidden" name="method" value="invite">@endif
    @if($editing) @method('PATCH') @endif
    @if($errors->any())
        <div role="alert" class="rounded-xl border border-red-500/40 p-4 text-sm text-red-700 dark:text-red-300">
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <section class="workspace-panel p-5 sm:p-6" aria-labelledby="user-details-title">
            <h2 id="user-details-title" class="mb-4 text-lg font-bold">Account details</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="space-y-2 text-sm font-semibold">
                    <span class="block">Full name</span>
                    <input name="name" required maxlength="120" value="{{ old('name', $account->name) }}" autocomplete="off" class="{{ $fieldClass }}">
                </label>
                <label class="space-y-2 text-sm font-semibold">
                    <span class="block">Email address</span>
                    <input name="email" type="email" required maxlength="255" value="{{ old('email', $account->email) }}" autocomplete="off" class="{{ $fieldClass }}">
                </label>
                <label class="space-y-2 text-sm font-semibold sm:col-span-2">
                    <span class="block">Phone number <span class="font-normal text-slate-500">(optional)</span></span>
                    <input name="phone" type="tel" maxlength="32" value="{{ old('phone', $account->phone) }}" placeholder="+855…" autocomplete="off" class="{{ $fieldClass }}">
                    <span class="block text-xs font-normal text-slate-600 dark:text-slate-400">Include the international country code.</span>
                </label>
                @if($sendInvitation)
                    <label class="space-y-2 text-sm font-semibold sm:col-span-2">
                        <span class="block">{{ __('invitations.language') }}</span>
                        <select name="invite_locale" required class="{{ $fieldClass }}">
                            @foreach(config('learning.supported_locales') as $locale => $label)
                                <option value="{{ $locale }}" @selected(old('invite_locale', app()->getLocale()) === $locale)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @elseif(!$editing)
                <label class="space-y-2 text-sm font-semibold">
                    <span class="block">Initial password</span>
                    <input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password" class="{{ $fieldClass }}">
                    <span class="block text-xs font-normal text-slate-600 dark:text-slate-400">At least 12 characters.</span>
                </label>
                <label class="space-y-2 text-sm font-semibold">
                    <span class="block">Confirm password</span>
                    <input name="password_confirmation" type="password" required minlength="12" maxlength="72" autocomplete="new-password" class="{{ $fieldClass }}">
                </label>
                @endif
            </div>
        </section>

    @unless($editing || $sendInvitation)
        <section class="workspace-panel p-5 sm:p-6" aria-labelledby="user-verification-title">
            <h2 id="user-verification-title" class="mb-2 text-lg font-bold">Contact verification</h2>
            <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">Use these options when you have already confirmed the user’s contact details.</p>
            <div class="flex flex-wrap gap-x-8 gap-y-2">
                <label class="flex min-h-10 items-center gap-2 text-sm"><input type="checkbox" name="verify_email" value="1" @checked(old('verify_email'))> Mark email verified</label>
                <label class="flex min-h-10 items-center gap-2 text-sm"><input type="checkbox" name="verify_phone" value="1" @checked(old('verify_phone'))> Mark phone verified</label>
            </div>
        </section>
    @endunless

    <section class="workspace-panel p-5 sm:p-6" aria-labelledby="user-access-title">
        <h2 id="user-access-title" class="mb-4 text-lg font-bold">Role and access</h2>
        <label class="block max-w-md space-y-2 text-sm font-semibold">
            <span class="block">Role</span>
            <select name="role" required class="{{ $fieldClass }}" data-user-role>
                @foreach($roles as $key => $label)
                    <option value="{{ $key }}" @selected(old('role', $account->role) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <fieldset class="mt-5 rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-admin-permissions>
            <legend class="px-1 text-sm font-bold">Admin permissions</legend>
            <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">These modules apply to the Admin role. Select the modules this account can access.</p>
            <div class="grid gap-x-5 gap-y-1 sm:grid-cols-2">
                @foreach($permissionOptions as $key => $label)
                    <label class="flex min-h-10 items-center gap-2 text-sm">
                        <input type="checkbox" name="permissions[]" value="{{ $key }}"
                            @checked(in_array($key, (array) old('permissions', $errors->any() ? [] : $selectedPermissions), true))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    </section>

    <div class="flex justify-end">
        <button type="submit" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white hover:bg-blue-800">
            {{ $editing ? 'Save changes' : ($sendInvitation ? __('invitations.send') : 'Create user') }}
        </button>
    </div>
</form>
@endif
@endsection
