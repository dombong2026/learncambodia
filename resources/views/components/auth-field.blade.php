@props([
    'id', 'name', 'label', 'type' => 'text', 'icon' => 'user-round',
    'autocomplete' => null, 'value' => '', 'required' => false,
    'maxlength' => null, 'minlength' => null, 'autofocus' => false,
])
<div class="auth-field relative">
    <span class="pointer-events-none absolute left-4 top-1/2 z-10 -translate-y-1/2 text-slate-400" aria-hidden="true">
        <x-workspace-icon :name="$icon" class="h-[18px] w-[18px]" />
    </span>
    <input
        id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" placeholder=" "
        @if($type !== 'password') value="{{ $value }}" @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if($maxlength) maxlength="{{ $maxlength }}" @endif
        @if($minlength) minlength="{{ $minlength }}" @endif
        @if($required) required @endif
        @if($autofocus) autofocus @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        {{ $attributes->class(['auth-field-input w-full rounded-xl border border-slate-300 bg-white pb-2 pt-6 pl-12 pr-12 text-sm text-slate-900 outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-[#426084] dark:bg-[#0c2342] dark:text-white', 'border-red-500' => $errors->has($name)]) }}
    >
    <label for="{{ $id }}" class="auth-field-label absolute left-12 text-sm font-medium text-slate-500 dark:text-slate-300">{{ $label }}</label>
    @if($type === 'password')
        <button type="button" data-password-toggle data-password-for="{{ $id }}" aria-pressed="false"
            aria-label="{{ __('auth.show_password') }}" data-show-label="{{ __('auth.show_password') }}"
            data-hide-label="{{ __('auth.hide_password') }}"
            class="absolute right-1.5 top-1/2 z-10 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-xl text-slate-500 transition hover:text-amber-600 focus-visible:outline-2 focus-visible:outline-amber-500 dark:text-slate-300">
            <x-workspace-icon name="eye" class="h-5 w-5" />
        </button>
    @endif
</div>
@error($name)
    <p role="alert" class="mt-1.5 text-xs text-red-600 dark:text-red-300">{{ $message }}</p>
@enderror
