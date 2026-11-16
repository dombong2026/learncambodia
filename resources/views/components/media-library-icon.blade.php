@props(['name'])
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('upload') <path d="M12 16V3m-5 5 5-5 5 5M4 16v4h16v-4"/> @break
        @case('search') <circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/> @break
        @case('image') <rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8" cy="9" r="1"/><path d="m4 17 4-4 3 3 4-5 5 6"/> @break
        @case('play') <path d="m9 5 11 7-11 7z" fill="currentColor" stroke="none"/> @break
        @case('expand') <path d="M8 4H4v4m12-4h4v4m0 8v4h-4m-8 0H4v-4"/> @break
        @case('more') <path d="M12 5h.01M12 12h.01M12 19h.01" stroke-width="3.2"/> @break
        @case('eye') <path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/> @break
        @case('edit') <path d="m15 5 4 4M4 20l5-1 11-11a2.8 2.8 0 0 0-4-4L5 15l-1 5Z"/> @break
        @case('trash') <path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v5m4-5v5"/> @break
        @case('close') <path d="m6 6 12 12M18 6 6 18"/> @break
    @endswitch
</svg>
