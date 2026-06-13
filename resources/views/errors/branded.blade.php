{{-- Minimal ink-frame error shell — deliberately self-contained: no app
     layout, no Livewire/Alpine, just the compiled stylesheet. Works even
     when the rest of the request lifecycle is broken. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · {{ config('app.name', 'HalalBizs') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-screen flex-col bg-paper text-ink antialiased">
    <header class="bg-ink" style="border-bottom: 1px solid var(--color-emerald-night);">
        <div class="mx-auto flex h-16 max-w-7xl items-center px-4">
            <a href="{{ url('/') }}" class="font-display text-xl font-bold text-paper">HalalBizs</a>
        </div>
    </header>

    <main class="flex flex-1 items-center justify-center px-4 py-16">
        <div class="w-full max-w-md text-center">
            <p class="font-display text-7xl font-extrabold leading-none tracking-tight" aria-hidden="true">@yield('code')</p>
            <h1 class="mt-4 font-display text-2xl font-bold">@yield('title')</h1>
            <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-ink-soft">@yield('message')</p>
            @yield('actions')
        </div>
    </main>

    <footer class="bg-ink">
        <p class="mx-auto max-w-7xl px-4 py-4 text-xs text-paper/64">© {{ now()->year }} HalalBizs. {{ __('All rights reserved.') }}</p>
    </footer>
</body>
</html>
