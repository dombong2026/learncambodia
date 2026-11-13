@extends('layouts.auth')
@section('title', 'Sign In')
@section('content')
<h1 class="text-center text-2xl font-extrabold">{{ \App\Support\Appearance::value('auth','login_heading') }}</h1>
<p class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">Sign in to your learning account.</p>
<form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">@csrf
    <div><label for="email" class="mb-2 block text-sm font-semibold">Email address</label><input id="email" name="email" type="email" autocomplete="email" maxlength="255" required value="{{ old('email') }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-700 dark:bg-slate-800">@error('email')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
    <div><label for="password" class="mb-2 block text-sm font-semibold">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-700 dark:bg-slate-800">@error('password')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror</div>
    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="checkbox" name="remember" value="1" class="rounded border-slate-300"> Remember me</label>
    <button type="submit" class="w-full rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-800">Sign In</button>
</form>
<p class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">New here? <a href="{{ route('register') }}" class="font-semibold text-blue-700 hover:underline dark:text-blue-400">Create an account</a></p>
@endsection
