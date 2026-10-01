<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f67ff">
    <meta name="application-name" content="Santovate CRM">
    <meta name="apple-mobile-web-app-title" content="Santovate CRM">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=crm-blue-1">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=crm-blue-1">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=crm-blue-1">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=crm-blue-1">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v=crm-blue-1">
    <title inertia>{{ config('app.name', 'Santovate CRM') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
