<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
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
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            /* Splash screen: shown from the first paint until React mounts, so
               the app never opens on a blank page. Styled inline on purpose —
               Tailwind is injected by JS in development and would arrive too
               late to be of any use here. */
            #app-splash {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 18px;
                background-color: oklch(1 0 0);
                transition: opacity 260ms ease-out;
            }

            html.dark #app-splash {
                background-color: oklch(0.145 0 0);
            }

            #app-splash[hidden] {
                display: none;
            }

            #app-splash.is-ready {
                opacity: 0;
                pointer-events: none;
            }

            #app-splash .splash-mark {
                width: 56px;
                height: 56px;
                border-radius: 14px;
                background-color: #4f46e5;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: splash-pulse 1.6s ease-in-out infinite;
            }

            #app-splash .splash-name {
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 14px;
                font-weight: 600;
                letter-spacing: 0.02em;
                color: oklch(0.42 0 0);
            }

            html.dark #app-splash .splash-name {
                color: oklch(0.72 0 0);
            }

            #app-splash .splash-track {
                width: 132px;
                height: 3px;
                border-radius: 999px;
                overflow: hidden;
                background-color: oklch(0.92 0 0);
            }

            html.dark #app-splash .splash-track {
                background-color: oklch(0.28 0 0);
            }

            #app-splash .splash-track span {
                display: block;
                width: 45%;
                height: 100%;
                border-radius: 999px;
                background-color: #4f46e5;
                animation: splash-slide 1.1s ease-in-out infinite;
            }

            @keyframes splash-pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(0.94); }
            }

            @keyframes splash-slide {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(222%); }
            }

            @media (prefers-reduced-motion: reduce) {
                #app-splash .splash-mark,
                #app-splash .splash-track span {
                    animation: none;
                }
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        {{-- Removed by resources/js/app.tsx once the first page has mounted. --}}
        <div id="app-splash" role="status" aria-live="polite" aria-label="Loading {{ config('app.name') }}">
            <span class="splash-mark" aria-hidden="true">
                <svg width="30" height="32" viewBox="0 0 40 42" fill="#ffffff" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M7 1H21.4142L30 9.58579V33C30 34.6569 28.6569 36 27 36H7C5.34315 36 4 34.6569 4 33V4C4 2.34315 5.34315 1 7 1ZM8 5V32H26V13H19V5H8ZM22 5.82843L25.1716 9H22V5.82843ZM10 16H24V19H10V16ZM10 22H24V25H10V22ZM10 28H19V31H10V28Z"/>
                    <path d="M31.5 24H38L34.75 30.5L31.5 24Z"/>
                    <path d="M33 15H36.5V23H33V15Z"/>
                </svg>
            </span>
            <span class="splash-name">{{ config('app.name') }}</span>
            <span class="splash-track" aria-hidden="true"><span></span></span>
        </div>

        <x-inertia::app />
    </body>
</html>
