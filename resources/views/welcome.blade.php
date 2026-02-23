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
   <body class="antialiased">
      <div id="app"></div>
    </body>
</html>
