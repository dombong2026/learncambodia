<form method="get" action="{{ $catalogUrl }}" class="assessment-catalog-filters" aria-label="{{ __('assessment_catalog.filters') }}">
    <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
    <div class="assessment-filter-search">
        <label class="sr-only" for="assessment-search">{{ __('assessment_catalog.search') }}</label>
        <input id="assessment-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="80" placeholder="{{ __('assessment_catalog.search') }}">
        <button type="submit">{{ __('pages.search') }}</button>
    </div>
    <details @if(count(array_diff_key($filters, ['q' => true]))) open @endif>
        <summary>{{ __('assessment_catalog.filters') }} <x-workspace-icon name="chevron-down" class="h-4 w-4" /></summary>
        <div class="assessment-filter-options">
            <label>{{ __('assessment_catalog.level') }}
                <select name="level">
                    <option value="">{{ __('assessment_catalog.any_level') }}</option>
                    @foreach(\App\Support\AssessmentCatalog::LEVELS as $level)
                        <option value="{{ $level }}" @selected(($filters['level'] ?? '') === $level)>{{ $level }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('assessment_catalog.skill') }}
                <select name="skill">
                    <option value="">{{ __('assessment_catalog.any_skill') }}</option>
                    @foreach(array_keys(\App\Support\AssessmentCatalog::SKILLS) as $skill)
                        <option value="{{ $skill }}" @selected(($filters['skill'] ?? '') === $skill)>{{ __('assessment_catalog.skills.'.$skill) }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('assessment_catalog.type') }}
                <select name="type">
                    <option value="">{{ __('assessment_catalog.any_type') }}</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" @selected((int) ($filters['type'] ?? 0) === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ __('assessment_catalog.duration') }}
                <select name="duration">
                    <option value="">{{ __('assessment_catalog.any_duration') }}</option>
                    @foreach(\App\Support\AssessmentCatalog::DURATIONS as $duration)
                        <option value="{{ $duration }}" @selected((int) ($filters['duration'] ?? 0) === $duration)>{{ __('assessment_catalog.up_to_minutes', ['minutes' => $duration]) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="assessment-filter-actions">
            <button type="submit">{{ __('assessment_catalog.apply') }}</button>
            @if($filters)<a href="{{ $catalogUrl }}">{{ __('assessment_catalog.clear') }}</a>@endif
        </div>
    </details>
    @if($filters)
        <p>{{ trans_choice('assessment_catalog.matches', $items->total()) }}</p>
    @endif
</form>
