@extends('layouts.workspace')
@section('title', 'Profile & Security')
@section('heading', 'Profile & Security')
@section('description', 'Manage your account name and password. Your role and email address cannot be changed from this form.')
@section('content')
@php $prefix = $role === 'admin' ? 'admin' : ($role === 'developer' ? 'developer' : 'account'); @endphp
<div class="grid gap-6 xl:grid-cols-2">
    <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-bold">Profile information</h2>
        <form method="POST" action="{{ route($prefix.'.profile.update') }}" class="mt-6 space-y-5">@csrf @method('PATCH')
            <div><label for="name" class="mb-2 block text-sm font-semibold">Display name</label><input id="name" name="name" maxlength="255" value="{{ old('name', auth()->user()->name) }}" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-950">@error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="mb-2 block text-sm font-semibold">Email address</label><input value="{{ auth()->user()->email }}" disabled class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-800"></div>
            <div><label class="mb-2 block text-sm font-semibold">Account role</label><input value="{{ ucfirst(auth()->user()->role) }}" disabled class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-800"></div>
            <div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">Save changes</button></div>
        </form>
    </section>
    <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-bold">Change password</h2>
        <form method="POST" action="{{ route($prefix.'.profile.password') }}" class="mt-6 space-y-5">@csrf @method('PATCH')
            <div><label for="current_password" class="mb-2 block text-sm font-semibold">Current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-950">@error('current_password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="new_password" class="mb-2 block text-sm font-semibold">New password</label><input id="new_password" name="password" type="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-950">@error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="password_confirmation" class="mb-2 block text-sm font-semibold">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-950"></div>
            <div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">Update password</button></div>
        </form>
    </section>
</div>
@endsection
