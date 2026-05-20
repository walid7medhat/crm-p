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
        <link rel="preload" href="{{ asset('assets/images/crm-bg.png') }}?v=2" as="image" type="image/png">
        <script>
            window.__API_BASE_URL__ = "{{ url('/api') }}";
            window.__APP_ORIGIN__ = "{{ url('') }}";
        </script>
        @vite('resources/js/main.js')
    </head>
    <body class="antialiased app-loader-active">
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
                background-color: #0b0736;
                background-image: url("{{ asset('assets/images/crm-bg.png') }}?v=2");
                background-size: cover;
                background-position: center center;
                background-repeat: no-repeat;
            }

            #app {
                position: relative;
                z-index: 1;
                min-height: 100vh;
                min-height: 100dvh;
                background: transparent;
            }

            @media (max-width: 768px) {
                .crm-bg-image {
                    background-position: center top;
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
        </script>
    </body>
</html>
