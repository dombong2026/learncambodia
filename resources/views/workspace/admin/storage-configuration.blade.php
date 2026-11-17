@php
    $profile = $profiles[$provider];
    $messages = $errors->getBag($provider.'Storage');
    $storageUrl = route('admin.system-settings.cloud-storage');
@endphp

<dialog id="cloud-storage-configuration-dialog" data-cloud-storage-dialog open class="cloud-storage-dialog" aria-labelledby="cloud-storage-dialog-title">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 id="cloud-storage-dialog-title" class="text-xl font-bold">Configure {{ $label }}</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Private bucket connection settings</p>
        </div>
        <a data-cloud-storage-close href="{{ $storageUrl }}" aria-label="Close configuration" class="inline-flex rounded-lg border border-slate-300 p-2 text-slate-700 dark:border-slate-600 dark:text-slate-200">
            <x-workspace-icon name="close" class="h-4 w-4" />
        </a>
    </div>

    @if(session('cloud_storage_saved_provider') === $provider)
        <p role="status" class="mt-4 rounded-xl border border-emerald-300 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:text-emerald-300">Settings saved. Close this dialog to test the connection.</p>
    @endif
    @if($activeDisk === $provider)
        <p class="mt-4 text-sm">Bucket name <strong>{{ $profile['bucket'] ?: 'Unavailable' }}</strong></p>
        <p class="mt-2 text-sm">This bucket is serving uploads. Its connection settings are locked.</p>
    @else
        @if($messages->any())
            <ul role="alert" class="system-settings-alert mt-4 rounded-xl border px-4 py-3 text-sm">
                @foreach($messages->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        @endif
        <form method="POST" action="{{ route('admin.system-settings.cloud-storage.save') }}" class="mt-4 space-y-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="provider" value="{{ $provider }}">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-semibold">Bucket name
                    <input name="bucket" type="text" maxlength="63" value="{{ $profile['bucket'] }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                </label>
                @if($provider === 'r2')
                    <label class="block text-sm font-semibold">S3 API endpoint
                        <input name="endpoint" type="url" value="{{ $profile['endpoint'] }}" required placeholder="https://ACCOUNT-ID.r2.cloudflarestorage.com" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                    </label>
                @else
                    <label class="block text-sm font-semibold">AWS region
                        <input name="region" type="text" maxlength="32" value="{{ $profile['region'] }}" required placeholder="ap-southeast-1" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                    </label>
                @endif
                @foreach(['access_key_id' => ['Access Key ID', 'off'], 'secret_access_key' => ['Secret Access Key', 'new-password']] as $field => [$fieldLabel, $autocomplete])
                    @php $fieldId = $provider.'-'.$field; @endphp
                    <div class="text-sm font-semibold">
                        <div class="flex items-center justify-between gap-2">
                            <label for="{{ $fieldId }}">{{ $fieldLabel }}</label>
                            <span id="{{ $fieldId }}-status" class="text-xs {{ $profile['credentialStatus'][$field] === 'Not set' ? 'text-slate-500 dark:text-slate-400' : 'text-emerald-700 dark:text-emerald-300' }}">{{ $profile['credentialStatus'][$field] }}</span>
                        </div>
                        <input id="{{ $fieldId }}" name="{{ $field }}" type="password" autocomplete="{{ $autocomplete }}" maxlength="256" aria-describedby="{{ $fieldId }}-status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                    </div>
                @endforeach
                @if($provider === 's3')
                    <label class="block text-sm font-semibold">Session token <span class="font-normal">(temporary credentials only)</span>
                        <input name="session_token" type="password" autocomplete="off" maxlength="4096" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                    </label>
                @endif
            </div>
            @if($profile['storedCredentials'])
                <p class="text-sm">Leave the credential fields blank to keep the saved keys.</p>
                @if($provider === 's3')
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="server_credentials" value="1">
                        Remove saved keys and use the server’s AWS credentials
                    </label>
                @endif
            @elseif($provider === 's3')
                <p class="text-sm">Leave the credential fields blank to use the server’s AWS credentials or IAM role.</p>
            @endif
            <div class="flex flex-wrap justify-end gap-2">
                <a href="{{ $storageUrl }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-600">Close</a>
                <button type="submit" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white hover:bg-blue-800">Save {{ $label }} settings</button>
            </div>
        </form>
    @endif
</dialog>
