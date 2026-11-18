@extends('layouts.auth')
@section('title', 'Social sign-in data deletion')
@section('auth_heading', 'Social sign-in data deletion')
@section('auth_intro', 'Remove a social sign-in connection or request deletion of account data.')

@section('content')
    <ol class="list-decimal space-y-4 pl-5 text-sm">
        <li>To remove your provider connection, sign in, open <a class="underline" href="{{ route('security.methods') }}">Sign-in and security</a>, confirm your password, and choose Disconnect next to the provider.</li>
        <li>If you signed up with a social account and need a password, use <a class="underline" href="{{ route('password.request') }}">Forgot your password?</a> before disconnecting.</li>
        <li>For a broader data deletion request, or if you cannot sign in, use <a class="underline" href="{{ route('information.show', 'contact') }}">Contact LearnCambodia</a>. Include your account email address and the name of the connected provider. Support may ask you to verify that you own the account.</li>
    </ol>
@endsection
