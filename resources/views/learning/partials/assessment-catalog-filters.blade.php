@php
    $searchLabel = __('assessment_catalog.'.($isQuiz ? 'search_quizzes' : 'search_exams'));
    $activeFilters = count(array_diff_key($filters, ['q' => true]));
@endphp

<form method="get" action="{{ $catalogUrl }}" class="assessment-catalog-filters" role="search" aria-label="{{ $searchLabel }}">
    <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
    <div class="assessment-filter-toolbar">
        <div class="assessment-filter-search">
            <label class="sr-only" for="assessment-search">{{ $searchLabel }}</label>
            <input id="assessment-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="80" placeholder="{{ $searchLabel }}">
            <button type="submit" aria-label="{{ __('pages.search') }}">
                <x-workspace-icon name="search" class="h-4 w-4" />
                <span>{{ __('pages.search') }}</span>
            </button>
        </div>
        <details>
            <summary>
                {{ __('assessment_catalog.filters') }}
                @if($activeFilters)
                    <span class="assessment-filter-count">{{ $activeFilters }}</span>
                @endif
                <x-workspace-icon name="chevron-down" class="h-4 w-4" />
            </summary>
            <div class="assessment-filter-panel">
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
                </div>
            </div>
        </details>
    </div>
    @if($filters)
        <p class="assessment-filter-feedback">
            {{ trans_choice('assessment_catalog.matches', $items->total()) }}
            <a href="{{ $catalogUrl }}">{{ __('assessment_catalog.clear') }}</a>
        </p>
    @endif
</form>
