@extends('layouts.workspace')
@section('title', $title)
@section('heading', $title)
@section('description', $description)

@push('workspace_styles')
    <link rel="stylesheet" href="{{ asset('css/system-settings.css') }}?v=cloud-storage-table">
@endpush

@if($section === 'security')
    @push('workspace_styles')
        <script src="{{ asset('js/account-passkeys.js') }}" defer></script>
    @endpush
@endif

@if($section === 'cloud-storage' && $selectedStorageProvider !== null)
    @push('workspace_styles')
        <script src="{{ asset('js/cloud-storage-dialog.js') }}" defer></script>
    @endpush
@endif

@section('content')
    <div class="max-w-5xl space-y-5">
        @error('probe')
            <p role="alert" class="system-settings-alert rounded-2xl border px-5 py-4 text-sm font-semibold">{{ $message }}</p>
        @enderror

        @if($section === 'branding')
            <div class="grid gap-4 sm:grid-cols-2">
                <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 system-settings-light-preview shadow-sm sm:p-6">
                    <h2 class="mb-4 text-sm font-bold">Light logo</h2>
                    <x-brand-logo surface="light" height="h-12" />
                    <p class="mt-3 text-sm">1,924 × 383 px · PNG</p>
                </section>
                <section class="min-w-0 rounded-2xl border border-slate-700 bg-[#0c2d5b] p-5 text-slate-100 shadow-sm sm:p-6">
                    <h2 class="mb-4 text-sm font-bold">Dark logo</h2>
                    <x-brand-logo surface="dark" height="h-12" />
                    <p class="mt-3 text-sm">1,902 × 375 px · PNG</p>
                </section>
            </div>
            <section class="rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-sm font-bold">Favicon</h2>
                <ul class="mt-4 flex flex-wrap gap-5">
                    @foreach([16, 32] as $size)
                        <li class="flex items-center gap-2 text-sm">
                            <img src="{{ asset('brand/favicon-'.$size.'.png') }}" width="{{ $size }}" height="{{ $size }}" alt="">
                            <span>{{ $size }} × {{ $size }} px · PNG</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($section === 'cloud-storage')
            @include('workspace.admin.storage-transfers')
            @if($selectedStorageProvider !== null)
                @include('workspace.admin.storage-configuration', ['provider' => $selectedStorageProvider, 'label' => $providers[$selectedStorageProvider]])
            @endif
        @else
            <dl class="divide-y divide-slate-200 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
                @foreach($rows as $row)
                    @php [$label, $detail, $value, $href] = $row + [3 => null]; @endphp
                    <div class="system-settings-row grid gap-3 px-5 py-5 sm:px-6">
                        <dt class="min-w-0">
                            <span class="block text-sm font-bold text-slate-900 dark:text-slate-100">{{ $label }}</span>
                            <span class="mt-1 block text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $detail }}</span>
                        </dt>
                        <dd class="system-settings-value">
                            @if($href)
                                <a href="{{ $href }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-blue-800 transition hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-slate-600 dark:text-blue-300 dark:hover:bg-slate-800">
                                    {{ $value }} <x-workspace-icon name="chevron-right" class="h-4 w-4" />
                                </a>
                            @else
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $value }}</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if($section === 'security')
            @include('workspace.security-methods')
        @elseif($section === 'social-auth')
            @include('workspace.google-method')
        @endif
    </div>
@endsection
