{{-- All branded pages (public, auth, and private account workspaces) share
     exactly the same cache-busted favicon and installed-app icon metadata. --}}
<link rel="icon" href="{{ asset('brand/favicon.ico') }}?v=20261114" sizes="any">
<link rel="icon" href="{{ asset('brand/favicon-32.png') }}?v=20261114" sizes="32x32" type="image/png">
<link rel="icon" href="{{ asset('brand/favicon-16.png') }}?v=20261114" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon.png') }}?v=20261114" sizes="180x180">
<link rel="manifest" href="{{ asset('brand/site.webmanifest') }}?v=20261114">
<meta name="application-name" content="LearnCambodia">
<meta name="apple-mobile-web-app-title" content="LearnCambodia">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="msapplication-TileImage" content="{{ asset('brand/mstile-144x144.png') }}?v=20261114">
<meta name="msapplication-TileColor" content="#0c2d5b">
