<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
        <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>

        <title>Alt CRM</title>
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#ffffff">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Alt CRM">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/pwa/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="192x192" href="/assets/images/pwa/icon-192.png">
        <link rel="apple-touch-startup-image" media="(device-width: 440px) and (device-height: 956px) and (-webkit-device-pixel-ratio: 3)" href="/assets/images/pwa/splash-1320x2868.png">
        <link rel="apple-touch-startup-image" media="(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3)" href="/assets/images/pwa/splash-1290x2796.png">
        <link rel="apple-touch-startup-image" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3)" href="/assets/images/pwa/splash-1179x2556.png">
        <link rel="apple-touch-startup-image" media="(device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3)" href="/assets/images/pwa/splash-1284x2778.png">
        <link rel="apple-touch-startup-image" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)" href="/assets/images/pwa/splash-1170x2532.png">
        <link rel="apple-touch-startup-image" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3)" href="/assets/images/pwa/splash-1125x2436.png">
        <link rel="apple-touch-startup-image" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2)" href="/assets/images/pwa/splash-828x1792.png">
        <link rel="apple-touch-startup-image" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)" href="/assets/images/pwa/splash-750x1334.png">

        <link rel="icon" type="image/x-icon" href="/assets/images/Fav icon.svg" />
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
            window.__API_BASE_URL__ = "{{ url('/api') }}";
            window.__APP_ORIGIN__ = "{{ url('') }}";
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
                background-color: #f5eef8;
                background-image: url("{{ asset('assets/images/crm-bg-light.jpg') }}?v=3");
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

            #boot-splash {
                position: fixed;
                inset: 0;
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f7f4fb;
                transition: opacity 0.22s ease;
            }

            .alt-boot {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 16px;
            }

            .alt-boot__mark {
                position: relative;
                width: 64px;
                height: 64px;
                display: grid;
                place-items: center;
            }

            .alt-boot__ring {
                position: absolute;
                inset: 0;
                border-radius: 50%;
                background: conic-gradient(from 0deg, rgba(115, 62, 135, 0.08), #733e87 40%, rgba(115, 62, 135, 0));
                -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #000 calc(100% - 2px));
                mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #000 calc(100% - 2px));
                animation: alt-boot-spin 0.7s linear infinite;
            }

            .alt-boot__core {
                width: 46px;
                height: 46px;
                border-radius: 50%;
                display: grid;
                place-items: center;
                background: #fff;
                color: #733e87;
                font-family: Inter, Montserrat, system-ui, sans-serif;
                font-size: 20px !important;
                font-weight: 800;
                letter-spacing: -0.04em;
                box-shadow: 0 8px 24px rgba(115, 62, 135, 0.16);
            }

            .alt-boot__name {
                margin: 0;
                font-family: Inter, Montserrat, system-ui, sans-serif;
                font-size: 15px !important;
                font-weight: 800;
                letter-spacing: 0.22em;
                color: #1a1528;
                line-height: 1;
            }

            .alt-boot__name span {
                color: #733e87;
            }

            .alt-boot__bar {
                width: 92px;
                height: 3px;
                border-radius: 999px;
                background: rgba(115, 62, 135, 0.12);
                overflow: hidden;
            }

            .alt-boot__bar i {
                display: block;
                width: 40%;
                height: 100%;
                border-radius: inherit;
                background: #733e87;
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
                    <span class="alt-boot__core">A</span>
                </div>
                <p class="alt-boot__name"><span>ALT</span> CRM</p>
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
