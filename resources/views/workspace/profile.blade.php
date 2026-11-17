@extends('layouts.workspace')
@section('title', 'Profile & Security')
@section('heading', 'Profile & Security')
@section('description', 'Manage your account details and password. Profile details entered here are private.')
@section('content')
@php $prefix = $role === 'admin' ? 'admin' : ($role === 'developer' ? 'developer' : ($role === 'instructor' ? 'instructor' : 'account')); @endphp
@if(in_array($role,['instructor','student'],true))
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-[#eed7a5] bg-[#fff8e8] px-5 py-4 dark:border-[#796339] dark:bg-[#403923]">
        <p class="text-sm font-bold text-[#153962] dark:text-[#ffe1a2]">{{ __('people.profile_editor_intro') }}</p>
        <a href="{{ route(($role==='student' ? 'account' : 'instructor').'.public-profile.edit') }}" class="rounded-xl bg-[#123460] px-5 py-2.5 text-xs font-black text-white hover:bg-[#265589]">{{ __('people.manage_profile') }}</a>
    </div>
@elseif($role==='admin')
    <div class="mb-5 flex justify-end"><a href="{{ route('admin.public-profiles.index') }}" class="rounded-xl bg-[#123460] px-5 py-2.5 text-xs font-black text-white">{{ __('people.profile_reviews') }}</a></div>
@endif
<div class="grid gap-6 xl:grid-cols-2">
    <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-bold">Profile information</h2>
        <form method="POST" action="{{ route($prefix.'.profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-5">@csrf @method('PATCH')
            <div class="flex flex-wrap items-center gap-4">
                @if(auth()->user()->avatar_path)
                    <img data-avatar-preview alt="" src="{{ route('workspace.avatar') }}" class="h-16 w-16 rounded-full object-cover">
                @else
                    <img data-avatar-preview alt="" hidden class="h-16 w-16 rounded-full object-cover">
                @endif
                <label class="builder-field">Profile photo
                    <input data-avatar-input type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
                    <span class="text-xs text-slate-500 dark:text-slate-400">JPEG, PNG or WebP, up to 4 MB.</span>
                </label>
            </div>
            @if(auth()->user()->avatar_path)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_avatar" value="1" @checked(old('remove_avatar'))> Remove photo</label>
            @endif
            @foreach(\Illuminate\Support\Arr::except($errors->getMessages(), ['current_password', 'password', 'password_confirmation', 'name']) as $messages)
                @foreach($messages as $message)<p role="alert" class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>@endforeach
            @endforeach
            <div><label for="name" class="mb-2 block text-sm font-semibold">Display name</label><input id="name" name="name" maxlength="255" value="{{ old('name', auth()->user()->name) }}" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-950">@error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="builder-field">First name
                    <input type="text" name="first_name" autocomplete="given-name" maxlength="100" value="{{ old('first_name', $details['first_name'] ?? '') }}">
                </label>
                <label class="builder-field">Last name
                    <input type="text" name="last_name" autocomplete="family-name" maxlength="100" value="{{ old('last_name', $details['last_name'] ?? '') }}">
                </label>
                <label class="builder-field">Gender
                    <select name="gender" class="rounded-xl border px-4 py-3">
                        <option value="">Select gender</option>
                        @foreach($genders as $value => $label)
                            <option value="{{ $value }}" @selected(old('gender', $details['gender'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="builder-field">Nationality
                    <select name="nationality" class="rounded-xl border px-4 py-3">
                        <option value="">Select country</option>
                        @foreach($countries as $code => $country)
                            <option value="{{ $code }}" @selected(old('nationality', $details['nationality'] ?? null) === $code)>{{ $country }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <label class="builder-field">Full address
                <textarea name="address" autocomplete="street-address" rows="3" maxlength="1000">{{ old('address', $details['address'] ?? '') }}</textarea>
            </label>
            <fieldset class="grid min-w-0 gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-2">
                <legend class="px-1 text-sm font-bold">Social media</legend>
                @foreach($social as $key => $label)
                    <label class="builder-field">{{ $label }}
                        <input type="url" name="social[{{ $key }}]" maxlength="300" value="{{ old('social.'.$key, $details['social'][$key] ?? '') }}">
                    </label>
                @endforeach
            </fieldset>
            <p class="text-sm font-semibold">User ID <span class="ml-2 tabular-nums">{{ auth()->user()->public_id }}</span></p>
            <div><label class="mb-2 block text-sm font-semibold">Email address</label><input value="{{ auth()->user()->email }}" disabled class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-800"></div>
            <div><label class="mb-2 block text-sm font-semibold">Account role</label><input value="{{ ucfirst(auth()->user()->role) }}" disabled class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-800"></div>
            <div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">Save changes</button></div>
        </form>
    </section>
    <section id="password" class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-bold">Change password</h2>
        <form method="POST" action="{{ route($prefix.'.profile.password') }}" class="mt-6 space-y-5">@csrf @method('PATCH')
            <div><label for="current_password" class="mb-2 block text-sm font-semibold">Current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-950">@error('current_password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="new_password" class="mb-2 block text-sm font-semibold">New password</label><input id="new_password" name="password" type="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-950">@error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
            <div><label for="password_confirmation" class="mb-2 block text-sm font-semibold">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 dark:border-slate-700 dark:bg-slate-950"></div>
            <div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">Update password</button></div>
        </form>
    </section>
</div>
@endsection
