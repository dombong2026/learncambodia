<div class="grid gap-3 border-t border-slate-200 pt-3 dark:border-slate-700">
    <div>
        <label for="policy_version" class="mb-1 block text-xs font-semibold">Policy version</label>
        <input id="policy_version" name="policy_version" value="{{ old('policy_version', $entry->policy_version) }}" maxlength="40" class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-xs dark:border-slate-700">
        @error('policy_version')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="policy_effective_on" class="mb-1 block text-xs font-semibold">Effective date</label>
        <input type="date" id="policy_effective_on" name="policy_effective_on" value="{{ old('policy_effective_on', $entry->policy_effective_on?->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-xs dark:border-slate-700">
        @error('policy_effective_on')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
