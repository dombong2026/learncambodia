@if(filled($materials['reading']))
    <div class="rich-public-content lesson-rich-content fl-reading-text">{!! $materials['reading'] !!}</div>
@elseif(filled($lesson->content))
    <div class="fl-reading-text fl-plain-text">{{ $lesson->content }}</div>
@else
    <p class="fl-muted">No written instructions for this lesson.</p>
@endif
