<fieldset class="my-3 grid gap-3 rounded-xl border border-slate-300 p-3 dark:border-slate-700 md:grid-cols-2" data-row>
    @foreach(['course' => 'Campaign course title', 'lesson' => 'Lesson title', 'type' => 'Media type', 'url' => 'Public HTTPS media URL'] as $key => $label)
        <label class="grid gap-1 text-sm">{{ $label }}
            @if($key === 'type')
                <select class="rounded-lg border p-2 dark:bg-slate-950" name="campaign[{{ $index }}][type]">
                    <option value="video">Video</option><option value="image" @selected(($row['type'] ?? '') === 'image')>Image</option>
                </select>
            @else
                <input class="rounded-lg border p-2 dark:bg-slate-950" required maxlength="{{ $key === 'url' ? 2048 : 140 }}" type="{{ $key === 'url' ? 'url' : 'text' }}" name="campaign[{{ $index }}][{{ $key }}]" value="{{ $row[$key] ?? '' }}">
            @endif
        </label>
    @endforeach
    <button type="button" class="justify-self-start text-sm underline" data-remove>Remove lesson</button>
</fieldset>
