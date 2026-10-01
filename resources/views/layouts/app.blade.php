@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="broadside">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-base-100 text-base-content antialiased">
        @auth
            <x-app-nav />
        @endauth

        <main class="mx-auto w-full max-w-[1200px] px-5 py-8">
            {{ $slot }}
        </main>
    </body>
</html>
