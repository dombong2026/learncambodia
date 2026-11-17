<a href="{{ $edit }}">Edit</a>
<a href="{{ route($manager.'.preview', $record) }}">Preview</a>
@if($active !== null)
    <form method="post" action="{{ route($manager.'.status', $record) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="active" value="{{ $active ? '0' : '1' }}">
        <button type="submit">{{ $active ? 'Deactivate' : 'Activate' }}</button>
    </form>
@elseif($record->status === 'pending_review')
    <a href="{{ route('admin.reviews.index', ['focus' => 'courses']) }}">Review publication</a>
@endif
<a href="{{ route($manager.'.users', $record) }}">Assign to user</a>
<form method="post" action="{{ route($manager.'.destroy', $record) }}" data-learning-delete="Delete “{{ $record->title }}”?">
    @csrf @method('DELETE')
    <button class="learning-manager-delete" type="submit">Delete</button>
</form>
