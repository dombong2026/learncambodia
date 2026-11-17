@if(session()->has(\App\Support\AccountPreview::KEY))
    <aside aria-label="Support session" class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-500 bg-amber-50 px-5 py-3 text-sm text-amber-950">
        <p><strong>Read-only support session</strong> · Viewing {{ auth()->user()->name }}</p>
        <form method="POST" action="{{ route('account-preview.stop') }}">
            @csrf
            <button class="rounded-lg bg-amber-900 px-4 py-2 font-bold text-white">Return to Admin</button>
        </form>
    </aside>
@endif
