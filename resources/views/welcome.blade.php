<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <title>Listing System</title>
        <script>
            window.__API_BASE_URL__ = "{{ url('/api') }}";
            window.__APP_ORIGIN__ = "{{ url('') }}";
        </script>
        @vite('resources/js/main.js')

       
    </head>
   <body class="antialiased"
         style="background-image: none !important;
                background-attachment: fixed;
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;">
      <video
        class="crm-bg-video"
        autoplay
        muted
        loop
        playsinline
        preload="auto"
        aria-hidden="true"
        tabindex="-1"
      >
        <source src="{{ asset('videos/vibecode.mp4') }}" type="video/mp4">
      </video>

      <style>
        .crm-bg-video{
          position: fixed;
          inset: 0;
          width: 100%;
          height: 100%;
          object-fit: cover;
          z-index: -1;
          pointer-events: none;
        }
        #app{
          position: relative;
          z-index: 1;
        }
      </style>

      <div id="app"></div>
    </body>
</html>
