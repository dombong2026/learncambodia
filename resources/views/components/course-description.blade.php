@props(['course'])

@php $html = \App\Support\EditorHtml::clean($course->description_html); @endphp
@if($html !== null)
    <div {{ $attributes->class(['rich-public-content course-description-content break-words']) }}>{!! $html !!}</div>
@else
    <p {{ $attributes->class(['whitespace-pre-line break-words']) }}>{{ $course->description }}</p>
@endif
