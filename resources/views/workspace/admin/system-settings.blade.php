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
        @endif
    </div>
@endsection
