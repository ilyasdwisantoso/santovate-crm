<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b2b22">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=4024">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=4024">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=4024">
    <title inertia>{{ config('app.name', 'Santovate CRM') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
