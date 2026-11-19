@extends('layouts.workspace')
@section('title', __('exam_types.title'))
@section('heading', __('exam_types.title'))
@push('workspace_styles')
    <link rel="stylesheet" href="{{ asset('css/exam-types.css') }}">
    @if($manage)
        <script defer src="{{ asset('js/exam-type-dialog.js') }}"></script>
    @endif
@endpush
@section('heading_action')
    <a class="learning-manager-control learning-manager-primary" href="{{ route('admin.exam-types.index', $listParameters + ['manage' => 'add']) }}">
        {{ __('exam_types.add') }}
    </a>
@endsection
@section('content')
    @if(!$manage && $errors->any())
        <p class="learning-manager-error" role="alert">{{ $errors->first() }}</p>
    @endif

    <section class="learning-manager exam-type-table" aria-label="{{ __('exam_types.title') }}">
        <div class="learning-manager-scroll" tabindex="0" role="region" aria-label="{{ __('exam_types.table') }}">
            <table>
                <colgroup>
                    <col class="exam-type-number-column">
                    <col class="exam-type-name-column">
                    <col>
                    <col class="exam-type-actions-column">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">{{ __('exam_types.number') }}</th>
                        <th scope="col">{{ __('exam_types.type') }}</th>
                        <th scope="col">{{ __('exam_types.description') }}</th>
                        <th scope="col">{{ __('exam_types.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($types as $type)
                        <tr>
                            <td class="learning-manager-number">{{ $types->firstItem() + $loop->index }}</td>
                            <th scope="row" class="exam-type-name">{{ $type->name }}</th>
                            <td class="exam-type-description">{{ filled($type->description) ? $type->description : '—' }}</td>
                            <td class="learning-manager-actions">
                                <button type="button" class="learning-manager-trigger" popovertarget="exam-type-actions-{{ $type->id }}"
                                    aria-label="{{ __('exam_types.actions_for', ['name' => $type->name]) }}">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="12" cy="19" r="1.9"/></svg>
                                </button>
                                <div id="exam-type-actions-{{ $type->id }}" popover class="learning-manager-menu">
                                    <a href="{{ route('admin.exam-types.index', $listParameters + ['manage' => 'edit', 'type' => $type->id]) }}">{{ __('exam_types.edit') }}</a>
                                    <a class="learning-manager-delete" href="{{ route('admin.exam-types.index', $listParameters + ['manage' => 'delete', 'type' => $type->id]) }}">{{ __('exam_types.delete') }}</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="learning-manager-empty" colspan="4">{{ __('exam_types.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($types->hasPages())
            <div class="learning-manager-footer">{{ $types->links() }}</div>
        @endif

        @if($manage)
            @include('workspace.admin.examination.exam-type-dialog')
        @endif
    </section>
@endsection
