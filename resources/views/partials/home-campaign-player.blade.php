@php
    $items = collect(json_decode(\Illuminate\Support\Facades\DB::table('homepage_campaigns')->where('id',1)->value('published') ?? '[]', true) ?: [])->values();
    $labels = match(app()->getLocale()) {
        'km' => ['វគ្គសិក្សាផ្សព្វផ្សាយ', 'មិនទាន់មានវគ្គសិក្សាផ្សព្វផ្សាយទេ។'],
        'zh' => ['推广课程', '暂无已发布的推广课程。'],
        default => ['Campaign courses', 'No campaign courses published yet.'],
    };
@endphp
<section class="lc-campaign lc-home-wrap" aria-label="{{ $labels[0] }}" data-campaign-player data-token="{{ csrf_token() }}">
    <div class="lc-campaign-screen" data-campaign-screen>
        @if($first = $items->first())
            @include('partials.campaign-lesson-content',['item'=>$first])
        @else
            <img class="lc-campaign-cover" src="{{ asset('images/home/hero-cutout.webp') }}" alt="">
        @endif
    </div>
    @if($items->count()>1)
        <div class="lc-slide-controls"><button type="button" data-step="-1" aria-label="{{ __('pagination.previous') }}">‹</button><output aria-live="polite">1 / {{ $items->count() }}</output><button type="button" data-step="1" aria-label="{{ __('pagination.next') }}">›</button></div>
    @endif
    <aside class="lc-campaign-list" aria-label="{{ $labels[0] }}">
        <h2>{{ $labels[0] }}</h2>
        @forelse($items->groupBy('course') as $course => $lessons)
            <details @if($loop->first) open @endif>
                <summary>{{ $course }}</summary>
                @foreach($lessons as $item)
                    <button type="button" data-media="{{ $loop->index }}" data-kind="{{ $item['type'] }}" aria-pressed="{{ $loop->parent->first && $loop->first ? 'true' : 'false' }}">
                        <span aria-hidden="true">▷</span><span>{{ $item['lesson'] }}</span>
                    </button><template>@include('partials.campaign-lesson-content',['item'=>$item])</template>
                @endforeach
            </details>
        @empty
            <p>{{ $labels[1] }}</p>
        @endforelse
    </aside>
</section>
