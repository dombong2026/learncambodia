<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Bucket connection and file transfer</h2>
    @forelse($providers as $provider => $label)
        <div class="system-settings-row grid gap-3 border-b border-slate-200 py-4 dark:border-slate-700">
            <h3 class="text-sm font-bold">{{ $label }}</h3>
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.system-settings.cloud-storage.probe') }}">
                    @csrf
                    <input type="hidden" name="provider" value="{{ $provider }}">
                    <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-600">Test {{ strtoupper($provider) }} connection</button>
                </form>
                @if(config('filesystems.managed') === 'local')
                    <form method="POST" action="{{ route('admin.system-settings.cloud-storage.transfer') }}">
                        @csrf
                        <input type="hidden" name="provider" value="{{ $provider }}">
                        <button name="operation" value="copy" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Copy local files</button>
                        <button name="operation" value="verify" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-600">Verify copy</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <p class="mt-3 text-sm">Save the settings for R2 or S3 to enable its connection test and file transfer controls.</p>
    @endforelse
    <p class="mt-4 text-sm">The connection test uses one temporary object. Copy retains local files. Verify compares checksums with the destination.</p>
    @if($transfers->isNotEmpty())
        <div class="mt-6 flex items-center justify-between gap-3">
            <h3 class="font-bold">Recent transfers</h3>
            <a href="{{ route('admin.system-settings.cloud-storage') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm dark:border-slate-600">Refresh status</a>
        </div>
        <ul class="mt-2 divide-y divide-slate-200 text-sm dark:divide-slate-700">
            @foreach($transfers as $transfer)
                <li class="py-3">
                    <p>{{ strtoupper($transfer->provider) }} · {{ ucfirst($transfer->operation) }} · {{ ucfirst($transfer->status) }}</p>
                    @if($transfer->summary)<p class="mt-1">{{ $transfer->summary }}</p>@endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
