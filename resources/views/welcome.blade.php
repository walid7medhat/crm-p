<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <title>Alt CRM</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
        <link rel="preload" href="{{ asset('videos/vibecode.mp4') }}?v=5" as="video" type="video/mp4">
        <script>
            window.__API_BASE_URL__ = "{{ url('/api') }}";
            window.__APP_ORIGIN__ = "{{ url('') }}";
        </script>
        @vite('resources/js/main.js')
    </head>
    <body class="antialiased app-loader-active">
        <video
            class="crm-bg-video"
            id="crm-bg-video"
            autoplay
            muted
            loop
            playsinline
            preload="auto"
            aria-hidden="true"
            tabindex="-1"
        >
            <source src="{{ asset('videos/vibecode.mp4') }}?v=5" type="video/mp4">
        </video>

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

            #app {
                position: relative;
                z-index: 1;
                min-height: 100vh;
                min-height: 100dvh;
                background: transparent;
            }

            @media (max-width: 768px) {
                .crm-bg-video {
                    object-position: center top;
                }
            }
        </style>

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

            (function startBackgroundVideo() {
                var video = document.getElementById('crm-bg-video');
                if (!video) return;
                var play = function () {
                    var p = video.play();
                    if (p && typeof p.catch === 'function') p.catch(function () {});
                };
                video.addEventListener('loadeddata', play, { once: true });
                video.addEventListener('canplay', play, { once: true });
                if (video.readyState >= 2) play();
                else play();
            })();
        </script>
    </body>
</html>
