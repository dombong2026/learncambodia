@props(['lesson'])

@php
    $html = \App\Support\EditorHtml::clean($lesson->content_html, lesson: true);
    if ($html !== null) {
        $html = preg_replace_callback(
            '/<figure([^>]* data-youtube="([A-Za-z0-9_-]{11})"[^>]*)>/',
            fn ($match) => '<figure'.$match[1].'><iframe src="https://www.youtube-nocookie.com/embed/'.$match[2]
                .'" title="YouTube lesson video" loading="lazy" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>',
            $html,
        );
    }
@endphp
@if($html !== null)
    <div {{ $attributes->class(['rich-public-content lesson-rich-content break-words']) }}>{!! $html !!}</div>
@else
    <p {{ $attributes->class(['whitespace-pre-wrap break-words']) }}>{{ $lesson->content }}</p>
@endif
