@extends('layouts.workspace')
@section('title', ucfirst($section) . ' Appearance')
@section('heading', ucfirst($section) . ' Appearance')
@section('description', 'Edit a constrained draft. No CSS, HTML, JavaScript or third-party integrations can be injected into the public website.')
@section('content')
<div class="max-w-4xl">
    <div class="mb-5 flex flex-wrap gap-3">
        <a href="{{ route('admin.appearance.index') }}" class="rounded-full border border-slate-300 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">← Appearance Manager</a>
        @if($revision)<span class="rounded-full bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">Current draft #{{ $revision->id }}</span>@endif
    </div>
    <form method="POST" action="{{ route('admin.appearance.store', $section) }}" class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        <div class="space-y-6">
            @foreach($fields as $key => $type)
                <div>
                    <label class="mb-2 block text-sm font-bold" for="appearance-{{ $key }}">{{ ucwords(str_replace('_', ' ', $key)) }}</label>
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
        <div class="mt-7 flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">Save new draft</button></div>
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
