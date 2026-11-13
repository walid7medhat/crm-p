@php
    /** @var \App\Support\Brand\BrandContext $brandContext */
    $brandContext = app(\App\Support\Brand\BrandContext::class);
    if (!$brandContext->has()) {
        $brandContext->set(app(\App\Support\Brand\BrandResolver::class)->resolveFromRequest(request()));
    }
    $brand = $brandContext->toFrontendArray();
    $brandColors = $brandContext->colors();
    $brandCssVars = $brandContext->cssVariableMap();
    $brandLogos = $brandContext->logos();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-brand="{{ $brand['key'] }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
        <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>

        <title>{{ $brand['document_title'] }}</title>
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="{{ $brandColors['primary'] ?? '#ffffff' }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ $brand['product_name'] }}">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ $brand['key'] === 'luxvant' ? ($brandLogos['favicon'] ?? '/assets/images/brands/luxvant/favicon.svg') : '/assets/images/pwa/apple-touch-icon.png' }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ $brand['key'] === 'luxvant' ? ($brandLogos['favicon'] ?? '/assets/images/brands/luxvant/favicon.svg') : '/assets/images/pwa/icon-192.png' }}">
        <link rel="apple-touch-startup-image" media="(device-width: 440px) and (device-height: 956px) and (-webkit-device-pixel-ratio: 3)" href="/assets/images/pwa/splash-1320x2868.png">
        <link rel="apple-touch-startup-image" media="(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3)" href="/assets/images/pwa/splash-1290x2796.png">
        <link rel="apple-touch-startup-image" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3)" href="/assets/images/pwa/splash-1179x2556.png">
        <link rel="apple-touch-startup-image" media="(device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3)" href="/assets/images/pwa/splash-1284x2778.png">
        <link rel="apple-touch-startup-image" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)" href="/assets/images/pwa/splash-1170x2532.png">
        <link rel="apple-touch-startup-image" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3)" href="/assets/images/pwa/splash-1125x2436.png">
        <link rel="apple-touch-startup-image" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2)" href="/assets/images/pwa/splash-828x1792.png">
        <link rel="apple-touch-startup-image" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)" href="/assets/images/pwa/splash-750x1334.png">

        <link rel="icon" type="image/svg+xml" href="{{ $brandLogos['favicon'] ?? '/assets/images/Fav icon.svg' }}" />
        {{-- Critical early tokens so splash / first paint match the host brand. --}}
        <style id="tenant-brand-vars-early">
            :root {
                @foreach ($brandCssVars as $var => $value)
                    @if ($value !== null && $value !== '')
                        {{ $var }}: {!! $value !!};
                    @endif
                @endforeach
            }
        </style>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" media="print" onload="this.media='all'">
        <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap"></noscript>
        @php
            $kanbanPreload = [];
            if (request()->is('kanban') && is_file(public_path('build/manifest.json'))) {
                $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true) ?: [];
                $seen = [];
                $walk = function ($key) use (&$walk, &$kanbanPreload, &$seen, $manifest) {
                    if (!$key || isset($seen[$key]) || $key === 'resources/js/main.js' || !isset($manifest[$key])) {
                        return;
                    }
                    $seen[$key] = true;
                    if (!empty($manifest[$key]['file'])) {
                        $kanbanPreload[$manifest[$key]['file']] = true;
                    }
                    foreach ($manifest[$key]['imports'] ?? [] as $import) {
                        $walk($import);
                    }
                };
                $walk('resources/js/pages/kanban.vue');
                $walk('resources/js/components/kanban/leadList/leads.vue');
            }
        @endphp
        @foreach (array_keys($kanbanPreload) as $file)
            <link rel="modulepreload" href="{{ asset('build/'.$file) }}" crossorigin>
        @endforeach
        <link rel="preload" href="{{ asset('assets/images/crm-bg-light.jpg') }}?v=3" as="image" type="image/jpeg">
        <script>
            {{-- Same-origin API for OIA + Luxvant hosts (never bake APP_URL / OIA hostname). --}}
            window.__API_BASE_URL__ = '/api';
            window.__APP_ORIGIN__ = window.location.origin;
            window.__BRAND__ = @json($brand);
            (function () {
                try {
                    if (window.location.pathname !== '/kanban') return;
                    var params = new URLSearchParams(window.location.search);
                    var hasBoardQuery = false;
                    params.forEach(function (value, key) {
                        if (key !== 'lead' && value) hasBoardQuery = true;
                    });
                    if (hasBoardQuery) return;
                    var token = localStorage.getItem('token') || localStorage.getItem('access_token') || sessionStorage.getItem('token');
                    if (!token && document.cookie) {
                        var parts = document.cookie.split('; ');
                        for (var i = 0; i < parts.length; i++) {
                            if (parts[i].indexOf('token=') === 0 || parts[i].indexOf('access_token=') === 0) {
                                token = decodeURIComponent(parts[i].split('=').slice(1).join('='));
                                break;
                            }
                        }
                    }
                    if (!token || !String(token).trim()) return;
                    var base = String(window.__API_BASE_URL__ || '').replace(/\/$/, '');
                    window.__leadBoardEarlyPrefetch = fetch(base + '/stages/kanban/stages-with-leads?per_page=15', {
                        headers: {
                            Accept: 'application/json',
                            Authorization: 'Bearer ' + String(token).trim()
                        },
                        credentials: 'same-origin'
                    }).then(function (res) {
                        if (!res.ok) throw new Error('Lead board prefetch failed');
                        return res.json();
                    }).then(function (body) {
                        var response = { data: body };
                        window.__leadBoardEarlyResponse = response;
                        return response;
                    });
                } catch (e) {}
            })();
        </script>
        @vite('resources/js/main.js')
        {{-- After Vite so host brand tokens win over resources/css/crm-theme.css defaults. --}}
        <style id="tenant-brand-vars">
            :root,
            html[data-brand="{{ $brand['key'] }}"] {
                @foreach ($brandCssVars as $var => $value)
                    @if ($value !== null && $value !== '')
                        {{ $var }}: {!! $value !!};
                    @endif
                @endforeach
            }
        </style>
    </head>
    <body class="antialiased">
        <div class="crm-bg-image" aria-hidden="true"></div>

        <style>
            html, body {
                margin: 0;
                padding: 0;
                overflow-x: hidden;
                max-width: 100vw;
                background: transparent;
            }

            body.app-loader-active {
                overflow: hidden;
            }

            .crm-bg-image {
                position: fixed;
                inset: 0;
                z-index: 0;
                pointer-events: none;
                background-color: {{ $brandColors['crm_bg_tint'] ?? '#f5eef8' }};
                @if (($brand['key'] ?? '') === 'luxvant')
                {{-- Shared wash asset + emerald brand tint (avoids OIA purple cast on Luxvant hosts). --}}
                background-image:
                    linear-gradient(
                        125deg,
                        rgba(238, 245, 244, 0.9) 0%,
                        rgba(232, 242, 241, 0.72) 48%,
                        rgba(243, 245, 245, 0.65) 100%
                    ),
                    url("{{ asset('assets/images/crm-bg-light.jpg') }}?v=3");
                @else
                background-image: url("{{ asset('assets/images/crm-bg-light.jpg') }}?v=3");
                @endif
                background-size: cover;
                background-position: center center;
                background-repeat: no-repeat;
            }

            .crm-bg-image::before {
                display: none !important;
            }

            #app {
                position: relative;
                z-index: 1;
                min-height: 100vh;
                min-height: 100dvh;
                background: transparent;
            }

            /* Scoped to the splash. A :root block here is parsed after the Vite
               CSS and would override resources/css/crm-theme.css for the whole app. */
            #boot-splash {
                --color-primary: {{ $brandColors['primary'] ?? '#733E87' }};
                --color-primary-rgb: {{ $brandColors['primary_rgb'] ?? '115, 62, 135' }};
                --color-background-plain: {{ $brandColors['background_plain'] ?? '#f6f7f6' }};
                --color-text-primary: {{ $brandColors['text'] ?? '#171717' }};
                --color-primary-soft: {{ $brandColors['primary_soft'] ?? '#f7f2f8' }};
                --gradient-primary: {!! $brandColors['gradient_primary'] ?? 'linear-gradient(135deg, rgba(255, 255, 255, 0.16) 0%, rgba(212, 175, 55, 0.38) 100%), rgb(115, 62, 135)' !!};
            }

            #boot-splash {
                position: fixed;
                inset: 0;
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--color-background-plain);
                transition: opacity 0.22s ease;
            }

            .alt-boot {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
            }

            .alt-boot__mark {
                position: relative;
                width: 36px;
                height: 36px;
                display: grid;
                place-items: center;
            }

            .alt-boot__ring {
                position: absolute;
                inset: 0;
                border-radius: 50%;
                background: conic-gradient(from 0deg, rgba(var(--color-primary-rgb), 0.08), var(--color-primary) 40%, rgba(var(--color-primary-rgb), 0));
                -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #000 calc(100% - 1.5px));
                mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #000 calc(100% - 1.5px));
                animation: alt-boot-spin 0.7s linear infinite;
            }

            .alt-boot__core {
                width: 22px;
                height: 22px;
                border-radius: 50%;
                display: grid;
                place-items: center;
                background: transparent;
                color: var(--color-primary);
                font-family: Inter, Montserrat, system-ui, sans-serif;
                font-size: 12px !important;
                font-weight: 800;
                letter-spacing: -0.04em;
                box-shadow: none;
            }

            .alt-boot__name {
                margin: 0;
                font-family: Inter, Montserrat, system-ui, sans-serif;
                font-size: 12px !important;
                font-weight: 700;
                letter-spacing: 0.14em;
                color: var(--color-text-primary);
                line-height: 1;
            }

            .alt-boot__name span {
                color: var(--color-primary);
            }

            .alt-boot__bar {
                width: 64px;
                height: 2px;
                border-radius: 999px;
                background: var(--color-primary-soft);
                overflow: hidden;
            }

            .alt-boot__bar i {
                display: block;
                width: 40%;
                height: 100%;
                border-radius: inherit;
                background: var(--gradient-primary) !important;
                animation: alt-boot-bar 0.7s ease-in-out infinite;
            }

            #boot-splash.is-done {
                opacity: 0;
                pointer-events: none;
            }

            @keyframes alt-boot-spin {
                to { transform: rotate(360deg); }
            }

            @keyframes alt-boot-bar {
                0% { transform: translateX(-120%); }
                100% { transform: translateX(280%); }
            }

            @media (prefers-reduced-motion: reduce) {
                .alt-boot__ring,
                .alt-boot__bar i {
                    animation: none;
                }
            }

            @media (max-width: 768px) {
                .crm-bg-image {
                    background-position: center top;
                }
            }
        </style>

        <div id="boot-splash" aria-hidden="true">
            <div class="alt-boot">
                <div class="alt-boot__mark">
                    <span class="alt-boot__ring"></span>
                    <span class="alt-boot__core">{{ $brand['product_mark'] }}</span>
                </div>
                @if ($brand['key'] === 'luxvant')
                    <p class="alt-boot__name"><span>LUXVANT</span> CRM</p>
                @else
                    <p class="alt-boot__name"><span>ALT</span> CRM</p>
                @endif
                <span class="alt-boot__bar" aria-hidden="true"><i></i></span>
            </div>
        </div>
        <div id="app"></div>
        <script>
            document.documentElement.style.background = 'transparent';
            document.body.style.background = 'transparent';
            document.body.style.backgroundImage = 'none';

            try {
                if (localStorage.getItem('token')) {
                    document.body.classList.add('app-has-video-bg');
                }
            } catch (e) { /* ignore */ }
        </script>
    </body>
</html>
