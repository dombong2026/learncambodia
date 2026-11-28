@if(\App\Support\PlayerSettings::audioPlayer() === 'premium')
    <link rel="stylesheet" href="{{ asset('css/audio-player.css') }}?v=5">
    <script defer src="{{ asset('js/audio-player.js') }}?v=6"></script>
@endif
