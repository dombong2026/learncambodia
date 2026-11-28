@php
    $editing = $manage === 'edit';
    $deleting = $manage === 'delete';
    $listUrl = route('admin.exam-types.index', $listParameters);
@endphp
<dialog open data-exam-type-dialog class="exam-type-dialog" aria-labelledby="exam-type-dialog-title">
    <div class="exam-type-dialog-heading">
        <h2 id="exam-type-dialog-title">{{ __('exam_types.'.$manage) }}</h2>
        <a href="{{ $listUrl }}" data-exam-type-close class="learning-manager-trigger" aria-label="{{ __('exam_types.close') }}">
            <x-workspace-icon name="close" class="h-4 w-4" />
        </a>
    </div>
    @if($errors->any())
        <p class="learning-manager-error" role="alert">{{ $errors->first() }}</p>
    @endif

    @if($deleting)
        <p class="exam-type-dialog-copy">{{ $inUse ? __('exam_types.in_use') : __('exam_types.confirm_delete', ['name' => $selectedType->name]) }}</p>
        @if($inUse)
            <div class="exam-type-dialog-actions">
                <a href="{{ $listUrl }}" class="learning-manager-control" autofocus>{{ __('exam_types.close') }}</a>
            </div>
        @else
            <form class="exam-type-dialog-actions" action="{{ route('admin.exam-types.destroy', ['id' => $selectedType->id] + $listParameters) }}" method="post">
                @csrf @method('DELETE')
                <a href="{{ $listUrl }}" class="learning-manager-control" autofocus>{{ __('exam_types.cancel') }}</a>
                <button type="submit" class="learning-manager-control exam-type-confirm">{{ __('exam_types.delete') }}</button>
            </form>
        @endif
    @else
        <form action="{{ $editing ? route('admin.exam-types.update', ['id' => $selectedType->id] + $listParameters) : route('admin.exam-types.store', $listParameters) }}"
            method="post" class="exam-type-form">
            @csrf
            @if($editing) @method('PATCH') @endif
            <label class="builder-field">{{ __('exam_types.name') }}
                <input name="name" value="{{ old('name', $selectedType?->name) }}" required maxlength="100" autofocus>
            </label>
            <label class="builder-field">{{ __('exam_types.description') }}
                <textarea name="description" maxlength="500" rows="4">{{ old('description', $selectedType?->description) }}</textarea>
            </label>
            <div class="exam-type-dialog-actions">
                <a href="{{ $listUrl }}" class="learning-manager-control">{{ __('exam_types.cancel') }}</a>
                <button type="submit" class="learning-manager-control learning-manager-primary">{{ __($editing ? 'exam_types.save' : 'exam_types.add') }}</button>
            </div>
        </form>
    @endif
</dialog>
