<fieldset class="promo-settings__track">
    <legend>Track {{ $number }}</legend>
    <input type="hidden" name="tracks[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="promo-settings__fields">
        <label>Track title <input type="text" name="tracks[{{ $index }}][title]" maxlength="100" value="{{ $row['title'] ?? '' }}"></label>
        <label>Artist or speaker <input type="text" name="tracks[{{ $index }}][artist]" maxlength="100" value="{{ $row['artist'] ?? '' }}"></label>
        <label>{{ $saved ? 'Replace audio file' : 'Audio file' }} <input type="file" name="tracks[{{ $index }}][audio]" accept=".mp3,.m4a,.ogg,.wav">
            @if($saved)<small>Leave blank to keep the current recording.</small>@endif
        </label>
        <label>Transcript <textarea name="tracks[{{ $index }}][transcript]" maxlength="4000" rows="3">{{ $row['transcript'] ?? '' }}</textarea></label>
    </div>
    <div class="promo-settings__actions">
        <button class="promo-settings__button" type="button" data-promo-row-action="up">Move up</button>
        <button class="promo-settings__button" type="button" data-promo-row-action="down">Move down</button>
        <button class="promo-settings__button" type="button" data-promo-row-action="remove">Remove track</button>
    </div>
</fieldset>
