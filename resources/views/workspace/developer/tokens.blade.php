@extends('layouts.workspace')
@section('title', 'API Tokens')
@section('heading', 'API Access Tokens')
@section('description', 'Issue and revoke short-lived credentials for read-only catalogue APIs. Plaintext tokens are never stored.')
@section('content')
@if(session('issued_token'))
<section class="mb-6 rounded-3xl border border-amber-300 bg-amber-50 p-6 dark:border-amber-900 dark:bg-amber-950">
    <h2 class="text-sm font-extrabold text-amber-900 dark:text-amber-200">Your new token — shown once</h2>
    <p class="mt-2 text-xs leading-6 text-amber-800 dark:text-amber-300">Copy and store this token securely. Reloading this page will hide it permanently.</p>
    <div class="mt-4 flex flex-wrap items-center gap-3">
        <code id="issued-token" class="min-w-0 flex-1 break-all rounded-xl border border-amber-200 bg-white p-3 text-xs text-slate-900 dark:border-amber-900 dark:bg-slate-900 dark:text-white">{{ session('issued_token') }}</code>
        <button type="button" data-copy-secret="issued-token" class="rounded-full bg-amber-900 px-5 py-3 text-sm font-semibold text-white">Copy token</button>
    </div>
</section>
@endif
<div class="grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
    <section class="self-start rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-extrabold">Create read-only token</h2>
        <p class="mt-2 text-xs leading-6 text-slate-500">Maximum 10 active tokens, each valid for 1–30 days. Creation requires your current password.</p>
        <form action="{{ route('developer.tokens.store') }}" method="POST" class="mt-6 space-y-5">@csrf
            <div><label for="token-name" class="mb-2 block text-sm font-semibold">Token name</label><input id="token-name" name="name" value="{{ old('name') }}" minlength="3" maxlength="60" required placeholder="My server integration" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="token-days" class="mb-2 block text-sm font-semibold">Expires in</label><select id="token-days" name="days" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="1">1 day</option><option value="7" selected>7 days</option><option value="30">30 days</option></select>@error('days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="token-password" class="mb-2 block text-sm font-semibold">Current password</label><input id="token-password" name="current_password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">@error('current_password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
            <div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">Generate token</button></div>
        </form>
    </section>
    <section class="min-w-0 rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-extrabold">Issued tokens</h2>
        <div class="mt-5 overflow-x-auto"><table class="w-full min-w-[540px] text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800"><tr><th class="py-3">Token</th><th class="py-3">Expires</th><th class="py-3">Last used</th><th class="py-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($tokens as $token)
                <tr><td class="max-w-[140px] break-words py-4 pr-3"><strong>{{ $token->name }}</strong><p class="mt-1 text-xs text-slate-500">catalog:read</p></td><td class="py-4 pr-3 text-xs">{{ $token->expires_at?->format('Y-m-d H:i') ?? 'Not set' }}</td><td class="py-4 pr-3 text-xs">{{ $token->last_used_at?->format('Y-m-d') ?? 'Never' }}</td><td class="py-4 text-right">
                    <form method="POST" action="{{ route('developer.tokens.destroy', $token->id) }}" onsubmit="return confirm('Revoke this token? Applications using it will stop working.')">@csrf @method('DELETE')<button type="submit" class="rounded-full border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 dark:border-red-900">Revoke</button></form>
                </td></tr>
            @empty
                <tr><td colspan="4" class="py-10 text-center text-sm text-slate-500">No developer tokens have been issued.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="mt-6">{{ $tokens->links() }}</div>
    </section>
</div>
@endsection
