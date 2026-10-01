<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <title>Alt CRM</title>
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#000000">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Alt CRM">
        <meta name="apple-mobile-web-app-status-bar-style" content="black">
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
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
        <link rel="preload" href="{{ asset('assets/images/crm-bg-light.jpg') }}?v=3" as="image" type="image/jpeg">
        <script>
            window.__API_BASE_URL__ = "{{ url('/api') }}";
            window.__APP_ORIGIN__ = "{{ url('') }}";
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
                background: #000000;
                transition: opacity 0.35s ease;
            }

            #boot-splash img {
                width: min(72vw, 320px);
                height: auto;
                display: block;
            }

            #boot-splash.is-done {
                opacity: 0;
                pointer-events: none;
            }

            @media (max-width: 768px) {
                .crm-bg-image {
                    background-position: center top;
                }
            }
        </style>

        <div id="boot-splash" aria-hidden="true">
            <img src="/assets/images/pwa/icon-512.png" alt="">
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
