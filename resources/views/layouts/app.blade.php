@props(['title' => null])

@php
    $native = app(\App\Support\NativeApp::class)->isRunning();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="broadside">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body @class([
        'min-h-screen bg-base-100 text-base-content antialiased',
        'native-shell' => $native,
    ])>
        @auth
            <x-app-nav :native="$native" />
        @endauth

        <main @class([
            'mx-auto w-full max-w-[1200px] px-5 py-8',
            'pb-24' => $native && auth()->check(),
        ])>
            {{ $slot }}
        </main>

        @auth
            @if ($native)
                <x-mobile-tabbar />
            @endif
        @endauth
    </body>
</html>
