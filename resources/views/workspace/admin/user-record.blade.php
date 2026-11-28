@extends('layouts.workspace')
@section('title', 'User #'.$account->public_id)
@section('heading', 'User #'.$account->public_id)
@section('heading_action')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">User Manager</a>
        @if(\App\Support\AdminAccess::canEdit(auth()->user(), $account) && $account->account_status !== 'deleted')
            <a href="{{ route('admin.users.access.edit', $account) }}" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white">Edit</a>
        @endif
    </div>
@endsection
@section('content')
    @php($invitation = $account->invitation)
    <dl class="workspace-panel grid gap-5 p-6 sm:grid-cols-2">
        @foreach(['Name' => $account->name, 'Email' => $account->email, 'Role' => \App\Support\AdminAccess::ROLES[$account->role] ?? $account->role, 'Status' => $account->isInvitedAccount() ? ($invitation?->statusLabel() ?? __('invitations.invited')) : ucfirst($account->account_status), 'Phone' => $account->phone ?? '—', 'Joined' => $account->isInvitedAccount() ? '—' : ($invitation?->accepted_at ?? $account->created_at)?->format('Y-m-d')] as $label => $value)
            <div>
                <dt class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                <dd class="mt-1 break-words">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
    <section class="workspace-panel mt-5 p-6" aria-labelledby="user-verification-heading">
        <h2 id="user-verification-heading" class="text-lg font-bold">{{ __('invitations.verification') }}</h2>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt>{{ __($account->hasVerifiedEmail() ? 'invitations.email_verified' : 'invitations.email_unverified') }}</dt>
                @if($account->email_verified_at)
                    <dd>{{ $account->email_verified_at->format('Y-m-d H:i') }}</dd>
                    @if($emailVerifier)
                        <dd>{{ __('invitations.by', ['name' => $emailVerifier]) }}</dd>
                    @elseif($invitation?->accepted_at?->equalTo($account->email_verified_at))
                        <dd>{{ __('invitations.via_invite') }}</dd>
                    @endif
                @endif
            </div>
            @if($account->phone)
                <div>
                    <dt>{{ __($account->phone_verified_at ? 'invitations.phone_verified' : 'invitations.phone_unverified') }}</dt>
                    @if($account->phone_verified_at)
                        <dd>{{ $account->phone_verified_at->format('Y-m-d H:i') }}</dd>
                        @if($phoneVerifier)<dd>{{ __('invitations.by', ['name' => $phoneVerifier]) }}</dd>@endif
                    @endif
                </div>
            @endif
            @if($account->isInvitedAccount() && $invitation)
                <div>
                    <dt>{{ __('invitations.email_sent_at') }}</dt>
                    <dd>{{ $invitation->sent_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>{{ __('invitations.expires_at') }}</dt>
                    <dd>{{ $invitation->expires_at->format('Y-m-d H:i') }}</dd>
                </div>
            @endif
        </dl>
    </section>
    @if($account->isAdministrator())
        <section class="workspace-panel mt-5 p-6" aria-labelledby="user-permissions-heading">
            <h2 id="user-permissions-heading" class="text-lg font-bold">Administrator permissions</h2>
            <ul class="mt-3 list-inside list-disc space-y-2 text-sm">
                @forelse(\App\Support\AdminAccess::grants($account) as $permission)
                    <li>{{ \App\Support\AdminAccess::PERMISSIONS[$permission] }}</li>
                @empty
                    <li>Overview and personal settings only</li>
                @endforelse
            </ul>
        </section>
    @endif
@endsection
