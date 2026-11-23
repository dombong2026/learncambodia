@php($promo = \App\Support\PromoPlayer::settings())
@if(\App\Support\PromoPlayer::visible($promo, $placement))
    <div @class(['promo-player-home' => $placement === 'homepage']) data-promo-placement="{{ $placement }}">
        @include('promo-player.deck', ['promo' => $promo, 'compact' => $placement === 'sidebar'])
    </div>
@endif
