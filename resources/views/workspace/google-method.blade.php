@error('google')<p role="alert" class="rounded-xl border border-red-300 px-4 py-3 text-sm">{{ $message }}</p>@enderror
<section class="mt-5 space-y-3 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Your Google account</h2>
    @if($account->google_subject)
        <p class="text-sm">Google account connected.</p>
        <form method="POST" action="{{ route('social.google.disconnect') }}">
            @csrf @method('DELETE')
            <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm dark:border-slate-600">Disconnect Google</button>
        </form>
    @elseif(\App\Support\GoogleSignIn::enabled())
        <form method="POST" action="{{ route('social.google.connect') }}">
            @csrf
            <button type="submit" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Connect Google</button>
        </form>
    @else
        <p class="text-sm">Configure the Google OAuth client on the server to connect a Google account.</p>
    @endif
    @if($account->isAdministrator())
        <p class="break-all text-sm">OAuth redirect URI <code>{{ \App\Support\GoogleSignIn::callback() }}</code></p>
    @endif
</section>
