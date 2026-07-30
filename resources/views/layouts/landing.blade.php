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
            <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-2 font-display text-lg font-medium tracking-tight {{ $light ? 'text-ink' : 'text-on-dark' }}">
                <x-ui.star-mark :size="22" class="text-brass" />
                HalalBizs
            </a>

            {{-- Offer only what applies. A logged-in applicant was being shown
                 "Log in", and someone already on the apply page was being shown
                 "Start Selling" pointing at the page they are on. --}}
            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                @guest
                    <a href="{{ route('login') }}" wire:navigate class="rounded-[var(--radius-control)] px-3 py-2 text-[13px] font-medium transition-colors {{ $light ? 'text-ink-soft hover:text-ink' : 'text-on-dark/80 hover:text-on-dark' }}">
                        {{ __('Log in') }}
                    </a>
                @else
                    <span class="hidden text-[13px] text-ink-faint sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-[var(--radius-control)] px-3 py-2 text-[13px] font-medium transition-colors {{ $light ? 'text-ink-soft hover:text-ink' : 'text-on-dark/80 hover:text-on-dark' }}">
                            {{ __('Log out') }}
                        </button>
                    </form>
                @endguest

                @unless (request()->routeIs('seller.apply', 'seller.status'))
                    <x-ui.button variant="brass" :href="route('seller.apply')">
                        {{ __('Start Selling') }}
                    </x-ui.button>
                @endunless
            </div>
        </div>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    {{-- ===== Minimal landing footer ===== --}}
    <footer class="border-t border-brass/20 bg-emerald-night px-4 py-6 text-center text-xs text-on-dark/64">
        © {{ now()->year }} HalalBizs.
        <a href="{{ route('home') }}" wire:navigate class="font-medium text-brass-tint transition-colors hover:text-on-dark">{{ __('Back to shopping') }}</a>
    </footer>

    @stack('scripts')
</body>
</html>
