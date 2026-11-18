@php
    $actor = auth()->user();
    $manageable = \App\Support\AdminAccess::canEdit($actor, $account);
    $menu = 'user-actions-'.$account->public_id;
    $active = $account->isActiveAccount();
    $deleted = $account->account_status === 'deleted';
@endphp
<button type="button" class="learning-manager-trigger" popovertarget="{{ $menu }}" aria-label="Actions for {{ $account->name }}">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="12" cy="19" r="1.9"/></svg>
</button>
<div id="{{ $menu }}" popover class="learning-manager-menu">
    @if($actor->is($account))
        <a href="{{ route('admin.profile.edit') }}">Edit</a>
    @elseif($manageable && $account->isInvitedAccount())
        <a href="{{ route('admin.users.access.edit', $account) }}">Edit</a>
        @if($invitationDeliveryEnabled)
            <form method="post" action="{{ route('admin.users.invitation.resend', $account) }}">
                @csrf
                <button type="submit">{{ __('invitations.resend') }}</button>
            </form>
        @endif
        @if($account->invitation && !$account->invitation->revoked_at)
            <form method="post" action="{{ route('admin.users.invitation.revoke', $account) }}">
                @csrf
                <button type="submit">{{ __('invitations.revoke') }}</button>
            </form>
        @endif
        <a href="{{ route('admin.users.actions', [$account, 'delete']) }}" class="learning-manager-delete">Delete</a>
    @elseif($manageable && ! $deleted)
        <a href="{{ route('admin.users.access.edit', $account) }}">Edit</a>
        <form method="post" action="{{ route('admin.users.status', $account) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ $active ? 'deactivated' : 'active' }}">
            <button type="submit">{{ $active ? 'Deactivate' : 'Activate' }}</button>
        </form>
        @if(\App\Support\AccountPreview::allowed($actor, $account))
            <a href="{{ route('admin.users.actions', [$account, 'sign-in-as']) }}">Sign In As</a>
        @endif
        <a href="{{ route('admin.users.actions', [$account, 'verify']) }}">Verify</a>
        <a href="{{ route('admin.users.actions', [$account, 'delete']) }}" class="learning-manager-delete">Delete</a>
    @elseif($manageable)
        <form method="post" action="{{ route('admin.users.status', $account) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="restore">
            <button type="submit">Restore</button>
        </form>
    @else
        <a href="{{ route('admin.users.show', $account) }}">View account</a>
    @endif
</div>
