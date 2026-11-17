@props(['name'])

<svg viewBox="0 0 24 24" fill="currentColor" {{ $attributes->class('h-5 w-5') }} aria-hidden="true" focusable="false">
    @switch($name)
        @case('facebook')
            <path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.8-4.7 4.53-4.7 1.31 0 2.69.24 2.69.24v2.97h-1.52c-1.49 0-1.95.93-1.95 1.88v2.27h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07Z"/>
            @break
        @case('youtube')
            <path d="M23.5 6.2a3 3 0 0 0-2.12-2.12C19.5 3.57 12 3.57 12 3.57s-7.5 0-9.38.51A3 3 0 0 0 .5 6.2 31.2 31.2 0 0 0 0 12a31.2 31.2 0 0 0 .5 5.8 3 3 0 0 0 2.12 2.12c1.88.51 9.38.51 9.38.51s7.5 0 9.38-.51a3 3 0 0 0 2.12-2.12A31.2 31.2 0 0 0 24 12a31.2 31.2 0 0 0-.5-5.8ZM9.55 15.57V8.43L15.82 12l-6.27 3.57Z"/>
            @break
        @case('linkedin')
            <path d="M20.5 2h-17A1.5 1.5 0 0 0 2 3.5v17A1.5 1.5 0 0 0 3.5 22h17a1.5 1.5 0 0 0 1.5-1.5v-17A1.5 1.5 0 0 0 20.5 2ZM8 19H5V9.5h3V19ZM6.5 8.15a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5ZM19 19h-3v-4.62c0-1.1-.02-2.52-1.53-2.52-1.54 0-1.78 1.2-1.78 2.44V19h-3V9.5h2.88v1.3h.04a3.15 3.15 0 0 1 2.84-1.56c3.03 0 3.55 2 3.55 4.59V19Z"/>
            @break
        @case('tiktok')
            <path d="M16.6 0c.38 3.14 2.17 5.06 5.27 5.26v3.59a9.82 9.82 0 0 1-5.24-1.63v7.82c0 9.93-10.83 13.04-15.15 5.92-2.78-4.58-1.06-12.63 7.92-12.95v3.8c-.67.11-1.38.27-2.04.5-1.98.68-3.1 1.94-2.8 4.18.56 4.28 8.4 5.54 7.75-2.82V0Z" transform="translate(1 1) scale(.91)"/>
            @break
        @case('share')
            <path d="m7.5 10.75 9-5M7.5 13.25l9 5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            <circle cx="5" cy="12" r="2.75" fill="none" stroke="currentColor" stroke-width="1.75"/>
            <circle cx="19" cy="4.5" r="2.75" fill="none" stroke="currentColor" stroke-width="1.75"/>
            <circle cx="19" cy="19.5" r="2.75" fill="none" stroke="currentColor" stroke-width="1.75"/>
            @break
    @endswitch
</svg>
