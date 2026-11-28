@switch($item['type'])
@case('youtube')
<iframe src="{{ str_replace('www.youtube.com/embed/','www.youtube-nocookie.com/embed/',$item['url']) }}" title="{{ $item['lesson'] }}" allowfullscreen></iframe>@break
@case('image')
<img src="{{ $item['url'] }}" alt="{{ $item['lesson'] }}">@break
@case('video')
<video controls playsinline preload="metadata" src="{{ $item['url'] }}" aria-label="{{ $item['lesson'] }}"></video>@break
@case('quiz')
@case('exam')
<form class="lc-reading" data-assessment data-key="{{ \App\Http\Controllers\Workspace\CampaignStudioController::lessonKey($item) }}" action="{{ route('campaign.check') }}">
<h3>{{ $item['lesson'] }}</h3>
@foreach($item['questions'] as $n=>$q)
<fieldset><legend>{{ $n+1 }}. {{ $q['prompt'] }}</legend>
@foreach($q['options'] as $value=>$choice)<label><input type="radio" required name="q{{ $n }}" value="{{ $value }}"> {{ $choice }}</label>@endforeach
<p data-result></p></fieldset>
@endforeach
<button type="submit">{{ $item['type']==='exam' ? 'Submit exam' : 'Check answers' }}</button><p aria-live="polite" data-score></p>
</form>@break
@default
<article class="lc-reading"><h3>{{ $item['lesson'] }}</h3>
@if($item['type']==='html'){!! \App\Support\EditorHtml::clean($item['body'] ?? '',true) !!}
@elseif($item['type']==='code')<small>{{ $item['language'] ?? '' }}</small><pre><code>{{ $item['body'] ?? '' }}</code></pre>
@else<p class="lc-lesson-text">{{ $item['body'] ?? '' }}</p>@endif
</article>
@endswitch
@if(!empty($item['feedback']))<details class="lc-takeaway"><summary>Lesson feedback</summary><p>{{ $item['feedback'] }}</p></details>@endif
