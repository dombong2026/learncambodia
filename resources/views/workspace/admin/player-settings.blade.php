@extends('layouts.workspace')

@section('title', 'Player Settings')
@section('heading', 'Player Settings')

@section('content')
    <div class="grid items-start gap-5 xl:grid-cols-2">
        <section class="workspace-panel space-y-4 p-5 sm:p-6" aria-labelledby="audio-settings-heading">
            <div>
                <h2 id="audio-settings-heading" class="text-lg font-extrabold">Audio player</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Choose the default player for assessment listening, lesson audio and editor previews.</p>
            </div>
            <form method="post" action="{{ route('admin.system-settings.player.update') }}" class="space-y-4">
                @csrf @method('PATCH')
                <fieldset class="space-y-3">
                    <legend class="sr-only">Default audio player</legend>
                    @foreach(\App\Support\PlayerSettings::AUDIO_PLAYERS as $key => $label)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-600">
                            <input class="mt-1" type="radio" name="audio_player" value="{{ $key }}" @checked(old('audio_player', $mode) === $key)>
                            <span>
                                <span class="block text-sm font-bold">{{ $label }}</span>
                                <span class="block text-sm text-slate-600 dark:text-slate-300">{{ $key === 'premium' ? 'Play, skip, seek, change speed and adjust volume.' : 'Use the audio controls provided by the browser.' }}</span>
                            </span>
                        </label>
                    @endforeach
                </fieldset>
                @error('audio_player')<p role="alert" class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                <button type="submit" class="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white">Save settings</button>
            </form>
        </section>
        <section class="workspace-panel space-y-4 p-5 sm:p-6" aria-labelledby="audio-preview-heading">
            <h2 id="audio-preview-heading" class="text-lg font-extrabold">Preview</h2>
            <audio controls preload="metadata" aria-label="Player preview" src="{{ asset('media/audio-player-preview.mp3') }}"></audio>
        </section>
    </div>
    <div class="mt-5">@include('promo-player.settings')</div>
@endsection
