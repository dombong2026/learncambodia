@extends('layouts.auth')
@section('title', 'Create Account')
@section('content')
<h1 class="text-center text-2xl font-extrabold">Create your account</h1>
<p class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">Start exploring English learning pathways.</p>
<form method="POST" action="{{ route('register.store') }}" class="mt-7 space-y-4">@csrf
    <div><label for="name" class="mb-1.5 block text-sm font-semibold">Full name</label><input id="name" name="name" type="text" maxlength="255" autocomplete="name" required value="{{ old('name') }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-700 dark:bg-slate-800">@error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
    <div><label for="email" class="mb-1.5 block text-sm font-semibold">Email address</label><input id="email" name="email" type="email" maxlength="255" autocomplete="email" required value="{{ old('email') }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-700 dark:bg-slate-800">@error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
    <div><label for="password" class="mb-1.5 block text-sm font-semibold">Password</label><input id="password" name="password" type="password" autocomplete="new-password" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-700 dark:bg-slate-800">@error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
    <div><label for="password_confirmation" class="mb-1.5 block text-sm font-semibold">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-700 dark:bg-slate-800"></div>
    <label class="flex items-start gap-2 text-xs leading-5 text-slate-600 dark:text-slate-400"><input name="terms" type="checkbox" value="1" required class="mt-1 rounded border-slate-300"> <span>I have read the <a class="font-semibold text-blue-700 dark:text-blue-400" href="{{ route('information.show','terms') }}" target="_blank" rel="noopener">draft Terms</a> and <a class="font-semibold text-blue-700 dark:text-blue-400" href="{{ route('information.show','privacy') }}" target="_blank" rel="noopener">draft Privacy Notice</a>.</span></label>
    @error('terms')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    <button type="submit" class="w-full rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-800">Create Account</button>
</form>
<p class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">Already registered? <a href="{{ route('login') }}" class="font-semibold text-blue-700 hover:underline dark:text-blue-400">Sign in</a></p>
@endsection
