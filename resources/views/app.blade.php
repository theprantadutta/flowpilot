<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f6f8fb;
            }

            html.dark {
                background-color: #080d17;
            }
        </style>

        <meta name="theme-color" content="#f6f8fb" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#080d17" media="(prefers-color-scheme: dark)">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">

        {{-- Public pages describe themselves for search engines and link previews; everything else stays out of search. --}}
        @isset($meta)
            <meta name="description" content="{{ $meta['description'] }}">
            <link rel="canonical" href="{{ $meta['url'] }}">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="{{ $meta['site_name'] }}">
            <meta property="og:title" content="{{ $meta['title'] }}">
            <meta property="og:description" content="{{ $meta['description'] }}">
            <meta property="og:url" content="{{ $meta['url'] }}">
            <meta property="og:image" content="{{ $meta['image'] }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:alt" content="{{ $meta['image_alt'] }}">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="{{ $meta['title'] }}">
            <meta name="twitter:description" content="{{ $meta['description'] }}">
            <meta name="twitter:image" content="{{ $meta['image'] }}">
            @foreach ($meta['schema'] as $schema)
                <script type="application/ld+json">@json($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)</script>
            @endforeach
        @else
            <meta name="robots" content="noindex, nofollow">
        @endisset

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ $meta['title'] ?? config('app.name', 'FlowPilot') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
