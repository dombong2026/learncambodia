@extends('layouts.workspace')
@section('title', 'Sign-in and security')
@section('heading', 'Sign-in and security')
@push('workspace_styles')
    <script src="{{ asset('js/account-passkeys.js') }}" defer></script>
@endpush
@section('content')
    <div class="max-w-5xl">
        @include('workspace.security-methods')
        @include('workspace.google-method')
        @include('workspace.social-accounts')
    </div>
@endsection
