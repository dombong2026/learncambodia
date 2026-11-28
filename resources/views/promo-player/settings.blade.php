@php
    $promo = \App\Support\PromoPlayer::settings();
    $submitted = old('revision') !== null;
    $rows = $submitted ? old('tracks', []) : $promo['tracks'];
    $rows = (is_array($rows) ? array_filter($rows, 'is_array') : []) ?: [[]];
    $savedTracks = array_column($promo['tracks'], null, 'id');
@endphp
<section class="workspace-panel promo-settings space-y-5 p-5 sm:p-6" id="promo-player" aria-labelledby="promo-settings-heading">
    <div>
        <h2 id="promo-settings-heading" class="text-lg font-extrabold">Promo Player</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Set up campaign audio for the homepage and the sidebars on course and article pages.</p>
    </div>

    <div class="space-y-2">
        <h3 class="text-sm font-bold">Preview</h3>
        @unless($promo['tracks'])
            <p class="text-sm text-slate-600 dark:text-slate-300">The preview uses the sample audio until you upload a track.</p>
        @endunless
        @include('promo-player.deck', ['promo' => $promo, 'preview' => true])
    </div>

    @if($errors->promo->any())
        <ul class="text-sm text-red-700 dark:text-red-300" role="alert">
            @foreach($errors->promo->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    @endif

    <form method="post" action="{{ route('admin.system-settings.player.promo.update') }}" enctype="multipart/form-data" data-promo-form>
        @csrf @method('PATCH')
        <input type="hidden" name="revision" value="{{ old('revision', $promo['revision']) }}">
        <fieldset>
            <legend>Placement</legend>
            <div class="promo-settings__checks">
                <label><input type="checkbox" name="homepage" value="1" @checked(old('homepage', !$submitted && $promo['homepage']))> Homepage</label>
                <label><input type="checkbox" name="sidebar" value="1" @checked(old('sidebar', !$submitted && $promo['sidebar']))> Course and article sidebars</label>
            </div>
        </fieldset>
        <fieldset>
            <legend>Appearance and playback</legend>
            <div class="promo-settings__fields">
                <label>Player heading <input type="text" name="title" maxlength="90" value="{{ old('title', $promo['title']) }}" required></label>
                <label>Accent color <input type="color" name="accent" value="{{ old('accent', $promo['accent']) }}" required></label>
                <label>Starting volume (%) <input type="number" name="volume" min="0" max="100" value="{{ old('volume', $promo['volume']) }}" required></label>
                <label>Background image <input type="file" name="background" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP. Up to 5 MB. Leave blank for a transparent surround.</small></label>
                <label>Campaign button label <input type="text" name="link_label" maxlength="40" value="{{ old('link_label', $promo['link_label']) }}" placeholder="Explore courses"></label>
                <label>Campaign button link <input type="text" name="link_url" maxlength="500" value="{{ old('link_url', $promo['link_url']) }}" placeholder="/courses or https://…"></label>
            </div>
            <div class="promo-settings__checks">
                <label><input type="checkbox" name="loop" value="1" @checked(old('loop', !$submitted && $promo['loop']))> Repeat playlist</label>
                @if($promo['background'])
                    <label><input type="checkbox" name="remove_background" value="1" @checked(old('remove_background'))> Remove the current background</label>
                @endif
            </div>
        </fieldset>
        <fieldset>
            <legend>Playlist</legend>
            <p class="promo-settings__hint">Upload up to eight MP3, WAV, M4A or OGG tracks. Each file can be up to 20 MB.</p>
            <div class="promo-settings__track-list" data-promo-track-list data-next-index="{{ max(array_map('intval', array_keys($rows))) + 1 }}">
                @foreach($rows as $index => $row)
                    @php($row = array_filter($row, 'is_string'))
                    @include('promo-player.track-fields', ['row' => $row, 'index' => $index, 'number' => $loop->iteration,
                        'saved' => isset($savedTracks[$row['id'] ?? ''])])
                @endforeach
            </div>
            <template>@include('promo-player.track-fields', ['row' => [], 'index' => 'TRACK_INDEX', 'number' => '', 'saved' => false])</template>
            <button class="promo-settings__button" type="button" data-promo-add>Add track</button>
        </fieldset>
        <button type="submit" class="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white">Save Promo Player</button>
    </form>
</section>
