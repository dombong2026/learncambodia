@php
    $routes = $itemType === 'module' ? 'modules' : 'lessons';
    $parameters = $itemType === 'module' ? [$course, $item] : [$course, $module, $item];
@endphp
<details class="builder-actions">
    <summary class="builder-button builder-icon-button" aria-label="Actions for {{ $item->title }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
    </summary>
    <div class="builder-action-list">
        <a href="{{ $editUrl }}">Edit {{ $itemType }}</a>
        <form method="POST" action="{{ route('admin.course-builder.'.$routes.'.duplicate', $parameters) }}">@csrf
            <button type="submit">Duplicate {{ $itemType }}</button>
        </form>
        <form data-builder-confirm="{{ $itemType === 'module' ? 'Delete this module and its lessons?' : 'Delete this lesson?' }}" method="POST" action="{{ route('admin.course-builder.'.$routes.'.destroy', $parameters) }}">@csrf @method('DELETE')
            <button type="submit" class="text-red-700 dark:text-red-300">Delete {{ $itemType }}</button>
        </form>
    </div>
</details>
