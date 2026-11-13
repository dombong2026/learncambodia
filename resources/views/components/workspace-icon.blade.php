@props(['name' => 'layout'])
@php
    $paths = [
        'layout' => 'M3 3h8v8H3zM13 3h8v5h-8zM13 10h8v11h-8zM3 13h8v8H3z',
        'home' => 'm3 10 9-7 9 7v11H3V10ZM9 21v-8h6v8',
        'search' => 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm6-2 4 4',
        'megaphone' => 'M3 10v4h4l9 5V5l-9 5H3ZM7 14l2 7h3',
        'palette' => 'M12 3a9 9 0 1 0 0 18h1a2 2 0 0 0 2-2 2 2 0 0 0-.4-1.2A2 2 0 0 1 16 14h2a3 3 0 0 0 3-3c0-4.4-4-8-9-8ZM7 11h.01M9 7h.01M14 6h.01M18 9h.01',
        'graduation' => 'm2 8 10-5 10 5-10 5L2 8ZM6 10v6c3.7 3 8.3 3 12 0v-6M22 8v7',
        'file' => 'M5 2h9l5 5v15H5zM14 2v5h5M9 12h6M9 16h6',
        'pen' => 'M4 20h4l12-12-4-4L4 16v4ZM14 6l4 4',
        'headphones' => 'M4 14v-3a8 8 0 0 1 16 0v3M4 14h4v7H6a2 2 0 0 1-2-2zM16 14h4v5a2 2 0 0 1-2 2h-2z',
        'shield' => 'm12 2 9 4v6c0 5-3.4 8.3-9 10-5.6-1.7-9-5-9-10V6l9-4ZM9 12l2 2 4-4',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM17 4a4 4 0 0 1 0 8M23 21v-2a4 4 0 0 0-4-4h-2',
        'wallet' => 'M3 5h17v15H3zM3 8V3h14M15 12h7v5h-7z',
        'settings' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM12 2v3M12 19v3M4.9 4.9 7 7M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1',
        'layers' => 'm3 8 9-5 9 5-9 5-9-5ZM3 12l9 5 9-5M3 16l9 5 9-5',
        'book' => 'M4 5a2 2 0 0 1 2-2h14v17H6a2 2 0 0 0 0-4h14M4 5v15a2 2 0 0 0 2 2h14',
        'code' => 'm8 8-4 4 4 4m8-8 4 4-4 4M14 4l-4 16',
        'key' => 'M8 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10ZM12 10h9v4h-3v3h-3v3h-3z',
        'activity' => 'M2 12h5l3-8 4 16 3-8h5',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'close' => 'M5 5l14 14M19 5 5 19',
        'chevron-down' => 'm6 9 6 6 6-6',
    ];
@endphp
<svg {{ $attributes->class(['h-[18px] w-[18px] shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] ?? $paths['layout'] }}"/></svg>
