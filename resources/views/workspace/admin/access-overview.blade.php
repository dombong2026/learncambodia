@extends('layouts.workspace')
@section('title', 'Overview')
@section('heading', 'Overview')
@section('description', 'Open the modules assigned to your account.')
@section('content')
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach($groups as $group)
            @continue($group['key'] === 'overview')
            <section class="workspace-panel p-5">
                <h2 class="flex items-center gap-2 font-bold"><x-workspace-icon :name="$group['icon']" /> {{ $group['title'] }}</h2>
                <ul class="mt-3 divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($group['items'] as $item)
                        <li><a class="flex items-center justify-between gap-3 py-3 text-sm text-blue-700 hover:underline dark:text-blue-300" href="{{ route($item['route'], $item['parameters'] ?? []) }}">{{ $item['label'] }} <x-workspace-icon name="chevron-right" /></a></li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endsection
