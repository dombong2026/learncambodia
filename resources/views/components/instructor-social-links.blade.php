@props(['profile', 'showName' => true])

@php($links = \App\Support\InstructorSocialLinks::allowed($profile->social_links))
@if($links)
    <div class="space-y-2">
        @if($showName)<p class="break-words text-xs font-semibold text-[#d1e0ef]">{{ $profile->display_name }}</p>@endif
        <ul class="flex flex-wrap gap-2" aria-label="{{ __('detail.instructor_social_links', ['name' => $profile->display_name]) }}">
            @foreach(\App\Support\InstructorSocialLinks::LABELS as $platform => $label)
                @isset($links[$platform])
                    <li>
                        <a class="grid h-10 w-10 place-items-center rounded-xl border border-white/25 text-[#ffe2ac] transition hover:border-[#efbe62] hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#efbe62]"
                           href="{{ $links[$platform] }}" target="_blank" rel="noopener noreferrer nofollow"
                           aria-label="{{ $label }} · {{ $profile->display_name }}" title="{{ $label }}">
                            <x-public-social-icon :name="$platform" />
                        </a>
                    </li>
                @endisset
            @endforeach
        </ul>
    </div>
@endif
