<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper text-ink">
    <div class="mx-auto flex min-h-screen max-w-7xl items-center justify-center">
        <div class="text-center">
            <h1 class="font-display text-4xl font-bold">{{ config('app.name') }}</h1>
            <p class="mt-2 text-ink-soft">Marketplace foundation ready — storefront coming in M2.</p>
        </div>
    </div>
</body>
</html>
