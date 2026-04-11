<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="Secure by design. Private by default. Chat without compromise" />
<meta name="theme-color" content="#ffffff" />
<meta name="robots" content="index, follow" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'MyChat') : config('app.name', 'MyChat') }}
</title>

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:title" content="{{ filled($title ?? null) ? $title.' - '.config('app.name', 'MyChat') : config('app.name', 'MyChat') }}" />
<meta property="og:description" content="Secure by design. Private by default. Chat without compromise" />
<meta property="og:image" content="{{ asset('open-graph.png') }}" />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />

<!-- Twitter -->
<meta property="twitter:card" content="summary_large_image" />
<meta property="twitter:url" content="{{ url()->current() }}" />
<meta property="twitter:title" content="{{ filled($title ?? null) ? $title.' - '.config('app.name', 'MyChat') : config('app.name', 'MyChat') }}" />
<meta property="twitter:description" content="Secure by design. Private by default. Chat without compromise" />
<meta property="twitter:image" content="{{ asset('open-graph.png') }}" />

<!-- Canonical URL -->
<link rel="canonical" href="{{ url()->current() }}" />

<link rel="icon" href="/favicon.svg" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/favicon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
