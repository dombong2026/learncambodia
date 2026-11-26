@extends('layouts.workspace')
@section('title', (\App\Support\Appearance::LABELS[$section] ?? ucfirst($section)) . ' Appearance')
@section('heading', ucfirst($section) . ' Appearance')
@section('description', 'Edit reviewed page headings and section copy. Public pages change only after independent approval; no unsafe scripts or third-party integrations.')
@section('content')
<div class="w-full">
    <div class="mb-5 flex flex-wrap gap-3">
        <a href="{{ route('admin.appearance.index') }}" class="rounded-full border border-slate-300 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">← Appearance Manager</a>
        @if($previewUrl = \App\Support\Appearance::preview($section))
            <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="rounded-full border border-[#d5a953] px-4 py-2.5 text-sm font-bold text-[#9b6d24] dark:text-[#f2ce8a]">View public page</a>
        @endif
        @if($revision)<span class="rounded-full bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">Current draft #{{ $revision->id }}</span>@endif
    </div>
    <form method="POST" action="{{ route('admin.appearance.store', $section) }}" class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @if($section === 'home')
            <div class="mb-7 rounded-2xl border border-slate-200 p-5 dark:border-slate-700">
                <h2 class="text-lg font-bold">{{ __('sunrise.layout_title') }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ __('sunrise.active_layout') }}: <strong>{{ \App\Support\Appearance::value('home','home_layout') === 'sunrise' ? 'Sunrise' : 'Classic' }}</strong></p>
                <fieldset class="mt-4 grid gap-4 sm:grid-cols-2">
                    <legend class="sr-only">{{ __('sunrise.layout_title') }}</legend>
                    @foreach(['classic','sunrise'] as $layout)
                        <label class="cursor-pointer rounded-2xl border border-slate-300 p-4 dark:border-slate-600">
                            <input type="radio" name="home_layout" value="{{ $layout }}" @checked(old('home_layout', $settings['home_layout'] ?? 'classic') === $layout) class="mr-2">
                            <strong>{{ ucfirst($layout) }}</strong>
                            <div class="my-4 h-20 rounded-xl {{ $layout === 'sunrise' ? 'bg-gradient-to-br from-amber-100 via-orange-100 to-emerald-200' : 'bg-gradient-to-br from-[#09234b] to-[#123962]' }}"></div>
                            <span class="text-sm text-slate-500">{{ __('sunrise.layout_'.$layout) }}</span>
                        </label>
                    @endforeach
                </fieldset>
                @error('home_layout')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="mt-4 flex flex-wrap gap-4 text-sm font-semibold">
                    <a class="underline" target="_blank" rel="noopener" href="{{ route('admin.appearance.home-preview','classic') }}">{{ __('sunrise.preview') }} Classic</a>
                    <a class="underline" target="_blank" rel="noopener" href="{{ route('admin.appearance.home-preview','sunrise') }}">{{ __('sunrise.preview') }} Sunrise</a>
                </div>
            </div>
        @endif
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach($fields as $key => $type)
                @continue($key === 'home_layout')
                <div>
                    <label class="mb-2 block text-sm font-bold text-[#0e315e] dark:text-white" for="appearance-{{ $key }}">{{ ucwords(str_replace('_', ' ', $key)) }}</label>
                    @if($type === 'bool')
                        <select id="appearance-{{ $key }}" name="{{ $key }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <option value="1" @selected((string) old($key, $settings[$key] ? '1' : '0') === '1')>Enabled</option>
                            <option value="0" @selected((string) old($key, $settings[$key] ? '1' : '0') === '0')>Disabled</option>
                        </select>
                    @elseif($type === 'font' || $type === 'scale')
                        @php $options = $type === 'font' ? ['system' => 'System sans-serif', 'serif' => 'Serif', 'mono' => 'Monospace'] : ['compact' => 'Compact', 'normal' => 'Normal', 'large' => 'Large']; @endphp
                        <select id="appearance-{{ $key }}" name="{{ $key }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                            @foreach($options as $option => $label)<option value="{{ $option }}" @selected(old($key, $settings[$key]) === $option)>{{ $label }}</option>@endforeach
                        </select>
                    @else
                        @php $limit = (int) explode(':', $type)[1]; @endphp
                        <textarea id="appearance-{{ $key }}" name="{{ $key }}" maxlength="{{ $limit }}" rows="{{ $limit > 220 ? 4 : 2 }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 dark:border-slate-700 dark:bg-slate-950">{{ old($key, $settings[$key] ?? '') }}</textarea>
                    @endif
                    @error($key)<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
        <div class="mt-7 flex justify-end"><button type="submit" class="rounded-xl bg-[#0e315e] px-6 py-3 text-sm font-bold text-white hover:bg-[#265184]">Save new draft</button></div>
    </form>
    <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-base font-extrabold">Review & activation</h2>
        <p class="mt-2 text-sm leading-7 text-slate-500">Drafts do not affect public pages. Submit the latest draft, then a different administrator must approve it before activation.</p>
        @if($revision)
            <form method="POST" action="{{ route('admin.appearance.submit', $revision) }}" class="mt-4 flex justify-end">@csrf
                <button type="submit" class="rounded-full border border-blue-500 px-5 py-3 text-sm font-bold text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-slate-800">Submit draft #{{ $revision->id }} for review</button>
            </form>
        @else
            <p class="mt-3 text-sm font-medium text-slate-500">Save an appearance draft to enable submission.</p>
        @endif
    </div>
</div>
@endsection
