<section id="bucket-configuration" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="bucket-configuration-title">
    <h2 id="bucket-configuration-title" class="text-lg font-bold">Bucket setup</h2>
    @if(!$canEditStorage)
        <p class="mt-2 text-sm">Confirm your password to enter the settings for your private R2 or S3 bucket.</p>
    @else
        <p class="mt-2 text-sm">Saved credentials are encrypted. Saving a bucket keeps the current storage disk active.</p>
        <div class="mt-4 space-y-4">
            @foreach(\App\Support\ManagedStorageSettings::PROVIDERS as $provider => $label)
                @php
                    $profile = $profiles[$provider];
                    $messages = $errors->getBag($provider.'Storage');
                @endphp
                <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" @if($messages->any() || !isset($providers[$provider]) || session('cloud_storage_saved_provider') === $provider) open @endif>
                    <summary class="cursor-pointer font-semibold">{{ $label }}</summary>
                    @if($activeDisk === $provider)
                        <p class="mt-3 text-sm">This bucket is serving uploads. Its connection settings are locked.</p>
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
                            <div class="flex justify-end">
                                <button type="submit" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white hover:bg-blue-800">Save {{ $label }} settings</button>
                            </div>
                        </form>
                    @endif
                </details>
            @endforeach
        </div>
    @endif
</section>
