@extends('layouts.workspace')
@php
    [$heading, $route] = match ($action) {
        'verify' => ['Verify user', 'admin.users.verify'],
        'delete' => ['Delete user', 'admin.users.destroy'],
        'sign-in-as' => ['Sign In As', 'admin.users.sign-in-as'],
    };
@endphp
@section('title', $heading)
@section('heading', $heading)
@section('description', $account->name.' · '.$account->email)
@section('heading_action')
    <a class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700" href="{{ route('admin.users.index') }}">Cancel</a>
@endsection
@section('content')
    <form method="POST" action="{{ route($route, $account) }}" class="workspace-panel max-w-xl space-y-5 p-6">
        @csrf
        @if($action === 'verify') @method('PATCH') @elseif($action === 'delete') @method('DELETE') @endif
        @if($errors->any())
            <ul role="alert" class="list-inside list-disc space-y-1 text-sm text-red-700 dark:text-red-300">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif
        @if($action === 'verify')
            @php $pending = !$account->hasVerifiedEmail() || ($account->phone && !$account->phone_verified_at); @endphp
            <p class="text-sm text-slate-600 dark:text-slate-400">Select the contact details you have independently confirmed.</p>
            <fieldset class="space-y-3">
                <legend class="sr-only">Contact details</legend>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="channels[]" value="email" @disabled($account->hasVerifiedEmail()) @checked($account->hasVerifiedEmail() || in_array('email', (array) old('channels'), true))>
                    Email · {{ $account->hasVerifiedEmail() ? 'Verified' : 'Pending' }}
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="channels[]" value="phone" @disabled(!$account->phone || $account->phone_verified_at) @checked($account->phone_verified_at || in_array('phone', (array) old('channels'), true))>
                    Phone · {{ $account->phone ? $account->phone.' · '.($account->phone_verified_at ? 'Verified' : 'Pending') : 'No number on file' }}
                </label>
            </fieldset>
            @if($pending)
                <button class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Save verification</button>
            @else
                <a class="text-sm font-bold text-blue-700 dark:text-blue-300" href="{{ route('admin.users.show', $account) }}">View account</a>
            @endif
        @else
            @if($action === 'delete')
                <p class="text-sm leading-relaxed">Delete removes this account from the regular directory and signs it out. You can find it later with the Deleted filter and restore it as deactivated.</p>
            @else
                <p class="text-sm leading-relaxed">Open a read-only view of this user’s workspace for up to 15 minutes. Use Return to Admin to end the session.</p>
            @endif
            <label class="block space-y-2 text-sm font-semibold">
                <span>Confirm your own password</span>
                <input name="current_password" type="password" autocomplete="current-password" required class="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
            </label>
            <button class="rounded-xl px-5 py-3 text-sm font-bold text-white {{ $action === 'delete' ? 'bg-red-700' : 'bg-blue-700' }}">{{ $heading }}</button>
        @endif
    </form>
@endsection
