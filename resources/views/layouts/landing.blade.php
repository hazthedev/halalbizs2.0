<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @stack('meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col overflow-x-clip bg-paper text-ink antialiased">

    {{-- ===== Minimal header — no shopping chrome =====
         Just the mark, a log-in link and a seller CTA. Two variants:
         · default (dark) — the landing: absolute over the hero so it reads
           on the night-sky art, scrolls away with the page.
         · 'light' — auth pages (register/login/etc., D-002 extended
           2026-07-25): static header on the light page background; the
           paper-on-dark text of the default would be invisible here. --}}
    @php $light = ($variant ?? null) === 'light'; @endphp
    <header class="{{ $light ? 'border-b border-line bg-surface' : 'absolute inset-x-0 top-0 z-40 bg-gradient-to-b from-emerald-night/70 via-emerald-night/20 to-transparent' }}">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-2 font-display text-lg font-bold tracking-tight {{ $light ? 'text-ink' : 'text-paper' }}">
                <x-ui.star-mark :size="22" class="text-brass" />
                HalalBizs
            </a>

            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <a href="{{ route('login') }}" wire:navigate class="rounded-lg px-3 py-2 text-[13px] font-semibold transition-colors {{ $light ? 'text-ink-soft hover:text-ink' : 'text-paper/80 hover:text-paper' }}">
                    {{ __('Log in') }}
                </a>
                <x-ui.button variant="brass" :href="route('seller.apply')">
                    {{ __('Start Selling') }}
                </x-ui.button>
            </div>
        </div>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    {{-- ===== Minimal landing footer ===== --}}
    <footer class="border-t border-brass/20 bg-ink px-4 py-6 text-center text-xs text-paper/64">
        © {{ now()->year }} HalalBizs.
        <a href="{{ route('home') }}" wire:navigate class="font-medium text-brass-tint transition-colors hover:text-paper">{{ __('Back to shopping') }}</a>
    </footer>

    @stack('scripts')
</body>
</html>
