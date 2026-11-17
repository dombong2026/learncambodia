<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="storage-providers-title">
    <div class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
        <h2 id="storage-providers-title" class="text-lg font-bold">Storage providers</h2>
        <p class="text-sm text-slate-600 dark:text-slate-300">Active storage <strong class="ml-1 text-slate-900 dark:text-slate-100">{{ $activeStorage }}</strong></p>
    </div>
    <div class="overflow-x-auto">
        <table class="cloud-storage-table text-sm" aria-labelledby="storage-providers-title">
            <thead class="border-y border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                <tr>
                    <th scope="col">Provider</th>
                    <th scope="col">Configuration</th>
                    <th scope="col" class="cloud-storage-actions">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @foreach($providers as $provider => $label)
                    @php $profile = $profiles[$provider]; @endphp
                    <tr class="system-settings-row">
                        <th scope="row" class="cloud-storage-name">
                            {{ $label }}
                            @if(filled($profile['bucket']))
                                <span class="mt-1 block font-normal text-slate-600 dark:text-slate-300">{{ $profile['bucket'] }}</span>
                            @endif
                        </th>
                        <td>
                            @if($profile['configured'])
                                <span class="font-semibold text-emerald-700 dark:text-emerald-300">Settings present</span>
                                <span class="mt-1 block text-xs text-slate-600 dark:text-slate-300">{{ $profile['source'] }}</span>
                            @else
                                <span class="text-slate-600 dark:text-slate-300">Settings missing</span>
                            @endif
                        </td>
                        <td class="cloud-storage-actions">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="{{ route('admin.system-settings.cloud-storage.configure', ['provider' => $provider]) }}" aria-label="Configure {{ $label }}" class="rounded-xl bg-blue-700 px-3 py-2 text-xs font-bold text-white hover:bg-blue-800">Configure</a>
                                @if($profile['configured'])
                                    <form method="POST" action="{{ route('admin.system-settings.cloud-storage.probe') }}">
                                        @csrf
                                        <input type="hidden" name="provider" value="{{ $provider }}">
                                        <button type="submit" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold dark:border-slate-600">Test {{ strtoupper($provider) }} connection</button>
                                    </form>
                                    @if($activeDisk === 'local')
                                        <form method="POST" action="{{ route('admin.system-settings.cloud-storage.transfer') }}" class="flex flex-wrap justify-end gap-2">
                                            @csrf
                                            <input type="hidden" name="provider" value="{{ $provider }}">
                                            <button name="operation" value="copy" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold dark:border-slate-600">Copy local files</button>
                                            <button name="operation" value="verify" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold dark:border-slate-600">Verify copy</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

@if($transfers->isNotEmpty())
<section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="storage-history-title">
        <div class="flex items-center justify-between gap-3">
            <h2 id="storage-history-title" class="font-bold">Recent transfers</h2>
            <a href="{{ route('admin.system-settings.cloud-storage') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm dark:border-slate-600">Refresh status</a>
        </div>
        <ul class="mt-2 divide-y divide-slate-200 text-sm dark:divide-slate-700">
            @foreach($transfers as $transfer)
                <li class="py-3">
                    <p>{{ $providers[$transfer->provider] ?? strtoupper($transfer->provider) }} · {{ ucfirst($transfer->operation) }} · {{ ucfirst($transfer->status) }}</p>
                    @if($transfer->summary)<p class="mt-1">{{ $transfer->summary }}</p>@endif
                </li>
            @endforeach
        </ul>
</section>
@endif
