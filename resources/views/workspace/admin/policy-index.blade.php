@extends('layouts.workspace')
@section('title', 'Policies Manager')
@section('heading', 'Policies Manager')
@section('description', 'Manage drafts, published documents, and their publication status.')

@section('heading_action')
    <a href="{{ route('admin.editorial.create', ['kind' => 'policies', 'lang' => app()->getLocale()]) }}" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white hover:bg-blue-800">Create</a>
@endsection

@section('content')
    <x-learning-manager-table :items="$entries" name="Policies"
        :columns="['ID', 'Title', 'Version', 'Status', 'Published', 'Updated', 'Actions']"
        search-label="Search by title or ID" empty="No policies have been created.">
        @foreach($entries as $entry)
            @php
                $parameters = ['kind' => 'policies', 'entry' => $entry, 'lang' => app()->getLocale()];
                $menu = 'policy-actions-'.$entry->id;
                $tone = match ($entry->status) {
                    'published' => 'positive',
                    'pending_review' => 'waiting',
                    'rejected' => 'negative',
                    default => 'neutral',
                };
                $viewUrl = $entry->status === 'published'
                    ? url()->query($entry->publicUrl(), ['lang' => app()->getLocale()])
                    : route('admin.editorial.preview', $parameters);
            @endphp
            <tr>
                <td><span class="learning-manager-id">{{ $entry->public_id ?? $entry->id }}</span></td>
                <th scope="row" class="learning-manager-title"><a href="{{ route('admin.editorial.edit', $parameters) }}">{{ $entry->title }}</a></th>
                <td class="learning-manager-number whitespace-nowrap">{{ $entry->policy_version ?? '—' }}</td>
                <td><span class="learning-manager-status" data-tone="{{ $tone }}">{{ ucfirst(str_replace('_', ' ', $entry->status)) }}</span></td>
                <td class="learning-manager-number whitespace-nowrap">
                    @if($entry->published_at)
                        <time datetime="{{ $entry->published_at->toAtomString() }}">{{ $entry->published_at->format('Y-m-d') }}</time>
                    @else
                        —
                    @endif
                </td>
                <td class="learning-manager-number whitespace-nowrap">
                    @if($entry->updated_at)
                        <time datetime="{{ $entry->updated_at->toAtomString() }}">{{ $entry->updated_at->format('Y-m-d') }}</time>
                    @else
                        —
                    @endif
                </td>
                <td class="learning-manager-actions">
                    <button type="button" class="learning-manager-trigger" popovertarget="{{ $menu }}" aria-label="Actions for {{ $entry->title }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="12" cy="19" r="1.9"/></svg>
                    </button>
                    <div id="{{ $menu }}" popover class="learning-manager-menu">
                        <a href="{{ route('admin.editorial.edit', $parameters) }}">Edit</a>
                        <a href="{{ $viewUrl }}">View</a>
                        @if(in_array($entry->status, ['draft', 'rejected', 'published'], true))
                            <form method="post" action="{{ route('admin.editorial.destroy', $parameters) }}" data-learning-delete="Delete this policy? Published content will immediately disappear from public pages.">
                                @csrf
                                @method('DELETE')
                                <button class="learning-manager-delete" type="submit">Delete</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-learning-manager-table>
@endsection
