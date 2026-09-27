<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#0b3347">
        <meta name="robots" content="noindex">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <title inertia>{{ config('app.name') }}</title>
        @fonts
        @viteReactRefresh
        @vite(['resources/css/portal.css', 'resources/js/portal.tsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
