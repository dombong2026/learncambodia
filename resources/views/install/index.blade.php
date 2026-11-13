<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <title>Secure installation · Learn Cambodia</title>
    @vite(['resources/css/app.css','resources/js/app.ts'])
</head>
<body class="min-h-screen bg-[#f4f6fa] px-4 py-12 text-slate-950 antialiased dark:bg-slate-950 dark:text-white">
    <main class="mx-auto max-w-3xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
        <header class="bg-[#0c2d5b] px-7 py-8 text-white sm:px-10">
            <p class="text-xs font-bold uppercase tracking-[.18em] text-[#f5c76e]">Learn Cambodia · One-time setup</p>
            <h1 class="mt-3 text-3xl font-black">Secure one-click installation</h1>
            <p class="mt-3 max-w-xl text-sm leading-7 text-[#d5e5f5]">This private installer configures an empty installation. It cannot upgrade or reset an existing school database.</p>
        </header>
        <div class="space-y-7 p-7 sm:p-10">
            @if($errors->any())
                <div role="alert" class="rounded-2xl border border-red-300 bg-red-50 p-5 text-sm leading-7 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                    <p class="font-bold">Please review the setup:</p>
                    <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <section>
                <h2 class="text-lg font-black">Server preflight</h2>
                <p class="mt-2 text-sm leading-7 text-slate-500">Set up your protected <code>.env</code> file and database before proceeding. The installer never displays database passwords or your secret token.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach([
                        'key'=>'Unique application encryption key','database'=>'Supported database configuration',
                        'https'=>'Secure application URL / HTTPS','debug'=>'Safe production debug setting',
                        'storage'=>'Writable private storage','assets'=>'Compiled frontend assets',
                    ] as $check=>$label)
                        <div class="flex items-start gap-3 rounded-xl bg-slate-50 px-4 py-3 text-xs font-semibold dark:bg-slate-950">
                            <span class="{{ $checks[$check] ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }} font-black">{{ $checks[$check] ? '✓' : '✕' }}</span>{{ $label }}
                        </div>
                    @endforeach
                </div>
            </section>
            @if(!$authorized)
                <form method="POST" action="{{ route('install.unlock') }}" class="space-y-4">@csrf
                    <h2 class="text-lg font-black">Operator verification</h2>
                    <p class="text-sm leading-7 text-slate-500">Enter the private setup token placed in your server environment. Never share it in URLs or screenshots.</p>
                    <label for="setup_token" class="block text-sm font-semibold">Private setup token</label>
                    <input id="setup_token" name="setup_token" type="password" required autocomplete="off" maxlength="256" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <div class="flex justify-end"><button type="submit" class="rounded-xl bg-[#0c2d5b] px-6 py-3 text-sm font-bold text-white hover:bg-[#174884]">Verify setup access</button></div>
                </form>
            @else
                <form method="POST" action="{{ route('install.complete') }}" class="space-y-5">@csrf
                    <h2 class="text-lg font-black">Create the first administrator</h2>
                    <p class="text-sm leading-7 text-slate-500">When you select Install, the platform migrates its configured empty database, imports approved reference categories and CEFR levels, creates the first admin account and locks this installer permanently.</p>
                    <div><label for="admin_name" class="mb-2 block text-sm font-bold">Full name</label><input id="admin_name" name="name" required maxlength="120" autocomplete="name" value="{{ old('name') }}" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950"></div>
                    <div><label for="admin_email" class="mb-2 block text-sm font-bold">Administrator email</label><input id="admin_email" name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('email') }}" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950"></div>
                    <div><label for="admin_password" class="mb-2 block text-sm font-bold">Strong password (12+ characters, mixed case, number, symbol)</label><input id="admin_password" name="password" type="password" required minlength="12" autocomplete="new-password" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950"></div>
                    <div><label for="admin_password_confirmation" class="mb-2 block text-sm font-bold">Confirm password</label><input id="admin_password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950"></div>
                    <label class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-6 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"><input type="checkbox" name="confirm_fresh_install" value="1" required class="mt-1"><span>I confirm this is a newly authorized empty school installation and I have reviewed database ownership, backups and the draft Terms/Privacy policies.</span></label>
                    <div class="flex justify-end"><button type="submit" @disabled(in_array(false,$checks,true)) class="rounded-xl bg-[#0c2d5b] px-7 py-3 text-sm font-extrabold text-white transition hover:bg-[#174884] disabled:cursor-not-allowed disabled:opacity-40">Install Learn Cambodia</button></div>
                </form>
            @endif
        </div>
    </main>
</body>
</html>
