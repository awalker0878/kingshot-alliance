<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="dark light">
        <meta name="description" content="Kingshot Alliance coordination platform">
        <meta name="theme-color" content="#0d3431">
        <meta name="application-name" content="Kingshot Alliance Command">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/png" sizes="192x192" href="/images/app-icons/icon-192.png">
        <link rel="apple-touch-icon" href="/images/app-icons/icon-192.png">
        <title inertia>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        @inertia
    </body>
</html>
