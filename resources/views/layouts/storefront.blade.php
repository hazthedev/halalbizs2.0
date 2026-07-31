<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="shopfront">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @stack('meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col overflow-x-clip bg-paper text-ink antialiased"
      x-data
      x-init="$store.cart.set({{ app(\App\Services\CartService::class)->itemCount(auth()->user()) }})">

    {{-- Top-of-page sentinel — the sticky header elevates once this scrolls out
         (IntersectionObserver in hbHeaderElevate, no scroll listener). --}}
    <div id="hb-top-sentinel" aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-6"></div>

    {{-- Auth/utility pages (login, sign-up, password, email verification) drop the
         shopping chrome — category strip + concierge are noise there. Extend this
         route list if other non-shopping pages should go bare too. --}}
    @php($bareChrome = request()->routeIs('login', 'register', 'password.request', 'password.reset', 'verification.notice'))

    {{-- ===== Occasion announcement bar (colors from ThemeSettings — never recolors actions) ===== --}}
    @php($themeSettings = app(\App\Settings\ThemeSettings::class))
    @php($hbListingCount = \Illuminate\Support\Facades\Cache::remember('hb.listing-count', 600, fn () => \App\Models\Product::query()->live()->count()))
    @if ($themeSettings->announcementActive())
        <div x-data="{ shown: true }"
             x-init="shown = sessionStorage.getItem('hb-announcement-dismissed') !== '1'"
             x-show="shown"
             class="relative w-full"
             style="background-color: {{ $themeSettings->announcement_bg }}; color: {{ $themeSettings->announcement_text_color }};">
            <div class="mx-auto flex min-h-11 max-w-7xl items-center justify-center px-12 py-1.5">
                <p class="text-center text-[13px] font-medium">{{ $themeSettings->announcementText(app()->getLocale()) }}</p>
            </div>
            <button type="button"
                    x-on:click="shown = false; sessionStorage.setItem('hb-announcement-dismissed', '1')"
                    class="absolute inset-y-0 right-1 my-auto flex size-11 items-center justify-center rounded-[var(--radius-control)] opacity-70 transition-opacity hover:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
                    aria-label="{{ __('Dismiss announcement') }}">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    {{-- ═══════════════ Revamp chrome (2026-07-30) ═══════════════
         Ported from the measured reference (library/architecture/
         halalbizs-revamp-ref-skeleton.md). Three rows: a dark trust ticker, a
         light main bar, then the department row. Every route, form field and
         action from the previous single dark bar is preserved.

         ⚠ Two things the reference shows that are deliberately NOT built here,
         because the app cannot yet honour them:
           · the "Verify a certificate" pill (needs the phase-2 certificate
             register — a button that resolves nothing is worse than no button)
           · its ticker's "2,140 VERIFIED SKUS" (a fabricated count). The count
             below is read from the database.
    --}}

    {{-- Row 1 — trust ticker. Mono, because these are counted facts. --}}
    <div class="bg-emerald-night text-on-dark-soft">
        {{-- flex-nowrap on purpose: with flex-wrap a too-wide ticker took a second
             row instead of shrinking, so the bar grew rather than clipping. --}}
        <div class="mx-auto flex max-w-[1400px] flex-nowrap items-center justify-between gap-x-6 px-4 py-1.5 lg:flex-wrap lg:gap-y-1 lg:px-12">
            {{-- whitespace-nowrap + min-w-0: on a phone all three claims wrapped to
                 two lines each, which is what made this bar 66px tall and pushed
                 the first product below the fold. The third claim is the longest,
                 so it is the one that stands down below sm; the row can then
                 never wrap, and clips rather than growing if a count gets wider. --}}
            <p class="flex min-w-0 items-center gap-2 overflow-hidden whitespace-nowrap font-mono text-[length:var(--text-micro)] uppercase tracking-[var(--tracking-label)]">
                <svg class="size-3 shrink-0 text-on-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                {{ __('Every seller verified') }}
                {{-- Measured, not guessed: at 390px a mono uppercase claim runs about
                     6.6px per character, so claim 1 alone (~155px) is all that fits
                     beside the 90px locale control inside 358px of usable width.
                     Keeping claim 2 as well came to ~380px and silently wrapped the
                     bar back into two rows, which is the whole thing being fixed. --}}
                <span class="hidden sm:contents">
                    <span aria-hidden="true" class="text-on-dark-faint">&middot;</span>
                    {{ trans_choice('{1} :count listing|[2,*] :count listings', $hbListingCount, ['count' => number_format($hbListingCount)]) }}
                    <span aria-hidden="true" class="text-on-dark-faint">&middot;</span>
                    {{ __('Ships nationwide from Kuala Lumpur') }}
                </span>
            </p>

            <div class="flex shrink-0 items-center gap-4">
                {{-- Locale: shown as a segmented pair like the reference, driven by
                     the same POST + `locale` field as before. --}}
                <form method="POST" action="{{ route('preferences.locale') }}" class="flex items-center rounded-[var(--radius-pill)] bg-emerald-card p-0.5">
                    @csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'en' ? 'ms' : 'en' }}">
                    @foreach (['en' => 'EN', 'ms' => 'BM'] as $code => $label)
                        {{-- py-1.5 not py-0.5: at 35x20px these pills were under the
                             24px WCAG 2.5.8 floor. 28px tall clears it without
                             turning the bar back into the thing we just shrank. --}}
                        @if (app()->getLocale() === $code)
                            <span class="rounded-[var(--radius-pill)] bg-on-dark px-3 py-1.5 font-mono text-[length:var(--text-micro)] font-medium tracking-[var(--tracking-label)] text-emerald-night">{{ $label }}</span>
                        @else
                            <button type="submit" class="rounded-[var(--radius-pill)] px-3 py-1.5 font-mono text-[length:var(--text-micro)] tracking-[var(--tracking-label)] text-on-dark-faint transition-colors duration-(--dur-micro) hover:text-on-dark" aria-label="{{ __('Switch language') }}">{{ $label }}</button>
                        @endif
                    @endforeach
                </form>

                {{-- Stands down below sm so the ticker keeps its width. The same
                     link lives in the footer's COMPANY column, so nothing on a
                     phone becomes unreachable. --}}
                <a href="{{ route('seller.apply') }}" wire:navigate class="hidden text-[length:var(--text-xs)] text-on-dark transition-colors duration-(--dur-micro) hover:text-white sm:inline">{{ __('Sell on HalalBizs') }}</a>
            </div>
        </div>
    </div>

    {{-- Row 2 — main bar. Light, hairline base, no shadow until elevated. --}}
    <header class="header-motion sticky top-0 z-40 border-b border-line bg-paper"
            x-data="hbHeaderElevate" x-bind:class="elevated && 'is-elevated'">
        {{-- gap-2 below sm: giving the search trigger a fixed 36px box (instead of
             letting flex-1 crush it to 24px) added 12px to this row and put a
             375px viewport back into overflow. The two 16px gaps were the slack. --}}
        <div class="mx-auto flex max-w-[1400px] items-center gap-2 px-4 py-3 sm:gap-4 lg:px-12">
            <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-2">
                <x-ui.star-mark :size="26" class="text-emerald" />
                <span class="flex flex-col leading-none">
                    <span class="font-display text-[22px] font-normal tracking-[var(--tracking-head)] text-ink-head">HalalBizs</span>
                    <span class="mt-1 hidden font-mono text-[length:var(--text-nano)] uppercase tracking-[var(--tracking-label-xl)] text-ink-faint sm:block">{{ __('Verified halal trade') }}</span>
                </span>
            </a>

            {{-- Search — same overlay dispatch as before, restyled to the
                 reference's wide pill with the action sitting inside it. --}}
            <button
                type="button"
                x-on:click="$dispatch('open-search')"
                {{-- Below sm this is a fixed icon button, not a shrunken field.
                     As `h-11 min-w-0 flex-1` with `pl-4 pr-1.5` it collapsed to
                     ~24px at 375px — 22px of that being padding — so the shrink-0
                     16px magnifier rendered hanging out of its own pill. --}}
                aria-label="{{ __('Search products, stores…') }}"
                class="group flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-pill)] border border-line bg-surface text-[length:var(--text-base)] text-ink-faint transition-colors duration-(--dur-micro) hover:border-line-strong sm:mx-auto sm:h-11 sm:w-auto sm:min-w-0 sm:max-w-2xl sm:flex-1 sm:shrink sm:justify-start sm:gap-2.5 sm:pl-4 sm:pr-1.5"
            >
                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <span class="hidden min-w-0 flex-1 truncate text-left sm:block">{{ __('Search products, stores…') }}</span>
                <kbd class="hidden rounded border border-line px-1.5 font-mono text-[length:var(--text-tiny)] sm:block">/</kbd>
                <span class="hidden shrink-0 rounded-[var(--radius-pill)] bg-emerald px-4 py-1.5 text-[length:var(--text-xs)] font-medium text-white transition-colors duration-(--dur-micro) group-hover:bg-emerald-deep sm:block">{{ __('Search') }}</span>
            </button>

            {{-- gap-1 below sm: signed in, this cluster carries four controls and
                 measured 214px, which ran the header 13px past a 390px viewport.
                 body is overflow-x-clip, so the cart count was being cut rather
                 than scrolled to. --}}
            <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                {{-- Concierge, mobile entry point (desktop uses the floating launcher). --}}
                @if (config('services.concierge.enabled', true) && ! $bareChrome)
                    <button type="button" x-on:click="$dispatch('open-concierge')"
                            class="flex size-9 items-center justify-center rounded-[var(--radius-pill)] text-emerald hover:bg-emerald-tint sm:hidden"
                            aria-label="{{ __('Ask the concierge') }}">
                        <x-ui.star-mark :size="20" />
                    </button>
                @endif

                {{-- Currency switcher — unchanged field name and action. --}}
                <form method="POST" action="{{ route('preferences.currency') }}" class="hidden sm:block" x-data>
                    @csrf
                    <select name="currency" x-on:change="$el.form.submit()"
                            class="cursor-pointer rounded-[var(--radius-pill)] border-0 bg-transparent py-2 pl-2 pr-6 font-mono text-[length:var(--text-xs)] text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald"
                            aria-label="{{ __('Display currency') }}">
                        @foreach (app(\App\Settings\GeneralSettings::class)->display_currencies as $code)
                            <option value="{{ $code }}" @selected(session('display_currency', 'MYR') === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                </form>

                @auth
                    <livewire:notification-bell context="storefront" />
                @endauth

                {{-- Account --}}
                @auth
                    <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                        <button type="button" x-on:click="open = !open" class="flex size-9 items-center justify-center rounded-[var(--radius-pill)] border border-line text-ink-soft transition-colors duration-(--dur-micro) hover:border-line-strong hover:text-ink sm:size-10" aria-label="{{ __('Account') }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.origin.top.right.duration.150ms
                             class="absolute right-0 top-12 w-56 rounded-[var(--radius-card)] border border-line bg-surface py-2">
                            <div class="border-b border-line px-5 py-3">
                                <p class="truncate text-[length:var(--text-base)] font-medium text-ink">{{ auth()->user()->name }}</p>
                                <p class="truncate font-mono text-[length:var(--text-tiny)] text-ink-faint">{{ auth()->user()->email }}</p>
                            </div>
                            <a href="{{ route('account.dashboard') }}" wire:navigate class="block px-5 py-2.5 text-[length:var(--text-base)] hover:bg-cream">{{ __('My account') }}</a>
                            <a href="{{ route('account.orders') }}" wire:navigate class="block px-5 py-2.5 text-[length:var(--text-base)] hover:bg-cream">{{ __('My orders') }}</a>
                            <a href="{{ route('account.messages') }}" wire:navigate class="block px-5 py-2.5 text-[length:var(--text-base)] hover:bg-cream">{{ __('Messages') }}</a>
                            <a href="{{ route('account.wishlist') }}" wire:navigate class="block px-5 py-2.5 text-[length:var(--text-base)] hover:bg-cream">{{ __('Wishlist') }}</a>
                            @if (auth()->user()->store?->isApproved())
                                <a href="{{ route('seller.dashboard') }}" class="block px-5 py-2.5 text-[length:var(--text-base)] hover:bg-cream">{{ __('Seller centre') }}</a>
                            @endif
                            @role('admin')
                                <a href="{{ route('admin.dashboard') }}" class="block px-5 py-2.5 text-[length:var(--text-base)] hover:bg-cream">{{ __('Admin panel') }}</a>
                            @endrole
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-line">
                                @csrf
                                <button type="submit" class="block w-full px-5 py-2.5 text-left text-[length:var(--text-base)] text-ink-soft hover:bg-cream">{{ __('Log out') }}</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" wire:navigate class="rounded-[var(--radius-pill)] px-3 py-2 text-[length:var(--text-xs)] text-ink-soft transition-colors duration-(--dur-micro) hover:text-ink">{{ __('Log in') }}</a>
                @endauth

                {{-- Cart — the reference's dark pill with a brass count. --}}
                <button type="button" x-on:click="$dispatch('open-mini-cart')" class="flex items-center gap-2 rounded-[var(--radius-pill)] bg-emerald-night px-2.5 py-2 text-[length:var(--text-xs)] font-medium text-on-dark transition-colors duration-(--dur-micro) hover:bg-emerald-deep sm:px-4" aria-label="{{ __('Cart') }}">
                    <svg class="size-4 shrink-0 sm:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                    <span class="hidden sm:block">{{ __('Cart') }}</span>
                    <span x-show="$store.cart.count > 0" x-cloak
                          x-bind:class="$store.cart.pulse && 'hb-pulse'"
                          class="flex h-5 min-w-5 items-center justify-center rounded-[var(--radius-pill)] bg-brass px-1 font-mono text-[length:var(--text-tiny)] font-medium text-emerald-night"
                          x-text="$store.cart.count"></span>
                </button>
            </div>
        </div>
    </header>

    {{-- Row 3 — departments. The campaign slot on the right only renders when a
         real announcement is configured; the reference's "RAMADAN SOURCING OPEN"
         is its own placeholder copy, not ours to invent. --}}
    @unless ($bareChrome)
        <nav class="border-b border-line bg-paper" aria-label="{{ __('Categories') }}">
            <div class="mx-auto flex max-w-[1400px] items-center gap-1 overflow-x-auto px-4 py-2.5 lg:px-12">
                <a href="{{ route('landing') }}" wire:navigate
                   class="flex shrink-0 items-center gap-1.5 rounded-[var(--radius-pill)] px-3 py-1.5 text-[length:var(--text-base)] text-emerald transition-colors duration-(--dur-micro) hover:bg-emerald-tint">
                    <x-ui.star-mark :size="14" />{{ __('Discover') }}
                </a>
                @if (config('live.enabled', true))
                    <a href="{{ route('live.index') }}" wire:navigate
                       class="flex shrink-0 items-center gap-1.5 rounded-[var(--radius-pill)] px-3 py-1.5 text-[length:var(--text-base)] text-danger transition-colors duration-(--dur-micro) hover:bg-danger/5">
                        <span class="size-1.5 animate-pulse rounded-full bg-danger"></span>{{ __('Live') }}
                    </a>
                @endif
                @foreach (\App\Models\Category::active()->whereNull('parent_id')->orderBy('position')->get() as $topCategory)
                    <a href="{{ route('category.show', $topCategory->slug) }}" wire:navigate
                       class="shrink-0 rounded-[var(--radius-pill)] px-3 py-1.5 text-[length:var(--text-base)] text-ink-soft transition-colors duration-(--dur-micro) hover:text-ink-head">
                        {{ $topCategory->getTranslation('name', app()->getLocale()) }}
                    </a>
                @endforeach

                @if ($themeSettings->announcementActive())
                    <span class="ml-auto hidden shrink-0 pl-6 font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label)] text-brass-deep lg:block">
                        {{ $themeSettings->announcementText(app()->getLocale()) }}
                    </span>
                @endif
            </div>
        </nav>
    @endunless

    {{-- ===== Main ===== --}}
    <main class="flex-1">
        {{ $slot }}
    </main>

    {{-- ═══════════════ Revamp footer (2026-07-30) ═══════════════
         Reference structure: dark green field, four columns under mono uppercase
         headers, with TRUST as a column of its own, then payment chips and a
         bilingual legal row. Every existing link is preserved; the columns are
         regrouped, not rewritten.

         Payment chips come from the PaymentMethod enum rather than the
         reference's hardcoded four (it shows DUITNOW and PICKUP, neither of
         which this app supports).
    --}}
    <footer class="mt-16 bg-emerald-night text-on-dark-soft">
        <div class="mx-auto grid max-w-[1400px] gap-10 px-4 py-14 sm:grid-cols-2 lg:grid-cols-4 lg:px-12"
             x-data="{ shown: false }" x-intersect.once="shown = true">

            {{-- Brand column --}}
            <div class="motion-reveal" x-bind:class="shown && 'revealed'">
                <p class="flex items-center gap-2 font-display text-[22px] text-on-dark">
                    <x-ui.star-mark :size="22" class="text-brass" />
                    HalalBizs
                </p>
                <p class="mt-3 max-w-[34ch] text-[length:var(--text-base)] leading-relaxed">{{ __('Malaysia’s trusted multi-vendor marketplace.') }}</p>

                {{-- Payment rails actually accepted. The reference shows
                     COD / DUITNOW / CARD / PICKUP; this app has COD and iPay88,
                     whose real rails are FPX, cards and e-wallets. Four chips as
                     in the reference, but every one of them true — a payment
                     method a buyer cannot use is a worse lie than a shorter row.
                     Driven off the enum so removing a method removes its chip. --}}
                <ul class="mt-5 flex flex-wrap gap-2">
                    @foreach (\App\Enums\PaymentMethod::cases() as $method)
                        @foreach ($method->rails() as $rail)
                            <li class="rounded-[var(--radius-pill)] border border-emerald-edge px-2.5 py-1 font-mono text-[length:var(--text-nano)] uppercase tracking-[var(--tracking-label)] text-on-dark-faint">{{ $rail }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>

            {{-- Marketplace column: the real department tree --}}
            <div class="motion-reveal" x-bind:class="shown && 'revealed'" style="animation-delay: 60ms">
                <p class="font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] text-on-dark-faint">{{ __('Marketplace') }}</p>
                <ul class="mt-4 space-y-2.5 text-[length:var(--text-base)]">
                    @foreach (\App\Models\Category::active()->whereNull('parent_id')->orderBy('position')->get() as $footerCategory)
                        <li><a href="{{ route('category.show', $footerCategory->slug) }}" wire:navigate class="transition-colors duration-(--dur-micro) hover:text-on-dark">{{ $footerCategory->getTranslation('name', app()->getLocale()) }}</a></li>
                    @endforeach
                    <li><a href="{{ route('landing') }}" wire:navigate class="transition-colors duration-(--dur-micro) hover:text-on-dark">{{ __('Discover HalalBizs') }}</a></li>
                </ul>
            </div>

            {{-- Trust column: its own column, as the reference has it --}}
            <div class="motion-reveal" x-bind:class="shown && 'revealed'" style="animation-delay: 120ms">
                <p class="font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] text-on-dark-faint">{{ __('Trust') }}</p>
                <ul class="mt-4 space-y-2.5 text-[length:var(--text-base)]">
                    <li><a href="{{ route('help.index') }}" wire:navigate class="transition-colors duration-(--dur-micro) hover:text-on-dark">{{ __('Help centre') }}</a></li>
                    <li><a href="{{ route('page.show', 'faq') }}" wire:navigate class="transition-colors duration-(--dur-micro) hover:text-on-dark">{{ __('FAQ') }}</a></li>
                    <li><a href="{{ route('page.show', 'refund-policy') }}" wire:navigate class="transition-colors duration-(--dur-micro) hover:text-on-dark">{{ __('Refund policy') }}</a></li>
                    <li><a href="{{ route('page.show', 'terms') }}" wire:navigate class="transition-colors duration-(--dur-micro) hover:text-on-dark">{{ __('Terms & conditions') }}</a></li>
                    <li><a href="{{ route('page.show', 'privacy') }}" wire:navigate class="transition-colors duration-(--dur-micro) hover:text-on-dark">{{ __('Privacy policy') }}</a></li>
                </ul>
            </div>

            {{-- Company column + newsletter --}}
            <div class="motion-reveal" x-bind:class="shown && 'revealed'" style="animation-delay: 180ms">
                <p class="font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] text-on-dark-faint">{{ __('Company') }}</p>
                <ul class="mt-4 space-y-2.5 text-[length:var(--text-base)]">
                    <li><a href="{{ route('page.show', 'about') }}" wire:navigate class="transition-colors duration-(--dur-micro) hover:text-on-dark">{{ __('About us') }}</a></li>
                    <li><a href="{{ route('seller.apply') }}" wire:navigate class="transition-colors duration-(--dur-micro) hover:text-on-dark">{{ __('Sell on HalalBizs') }}</a></li>
                </ul>

                <form method="POST" action="{{ route('newsletter.subscribe') }}" class="mt-6 flex gap-2">
                    @csrf
                    <input type="email" name="email" required placeholder="{{ __('Your email') }}"
                           class="h-10 w-full rounded-[var(--radius-pill)] border border-emerald-edge bg-emerald-card px-4 text-[length:var(--text-base)] text-on-dark placeholder:text-on-dark-faint focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brass">
                    <button type="submit" class="h-10 shrink-0 rounded-[var(--radius-pill)] bg-on-dark px-5 text-[length:var(--text-xs)] font-medium text-emerald-night transition-colors duration-(--dur-micro) hover:bg-white">{{ __('Subscribe') }}</button>
                </form>
                @if (session('newsletter.status'))
                    <p class="mt-2 text-[length:var(--text-base)] text-on-dark">{{ session('newsletter.status') }}</p>
                @endif
            </div>
        </div>

        <div class="border-t border-emerald-edge">
            <div class="mx-auto flex max-w-[1400px] flex-wrap items-center justify-between gap-2 px-4 py-5 lg:px-12">
                <p class="font-mono text-[length:var(--text-micro)] uppercase tracking-[var(--tracking-label)] text-on-dark-faint">&copy; {{ now()->year }} HalalBizs. {{ __('All rights reserved.') }}</p>
                <p class="font-mono text-[length:var(--text-micro)] uppercase tracking-[var(--tracking-label)] text-on-dark-faint">English &middot; Bahasa Melayu</p>
            </div>
        </div>
    </footer>

    {{-- Toasts (ink surface, always top-right) --}}
    <div class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-end gap-2 px-4" aria-live="polite">
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="-translate-y-2 opacity-0"
                 x-transition:leave="transition duration-100 ease-in" x-transition:leave-end="opacity-0"
                 class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-[var(--radius-card)] border border-brass/20 bg-emerald-night px-5 py-4 text-sm text-on-dark shadow-pop">
                <svg x-show="toast.type === 'success'" class="size-4 shrink-0 text-emerald" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                <svg x-show="toast.type === 'error'" class="size-4 shrink-0 text-danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                <span x-text="toast.message" class="flex-1"></span>
                <a x-show="toast.actionLabel && toast.actionEvent === 'view-cart'" href="{{ route('cart') }}" wire:navigate class="shrink-0 font-medium text-emerald" x-text="toast.actionLabel"></a>
                <button type="button" x-show="toast.actionLabel && toast.actionEvent && toast.actionEvent !== 'view-cart'"
                        x-on:click="Livewire.dispatch(toast.actionEvent, toast.actionPayload ?? {}); $store.toasts.dismiss(toast.id)"
                        class="shrink-0 font-medium text-emerald" x-text="toast.actionLabel"></button>
                <button type="button" x-on:click="$store.toasts.dismiss(toast.id)" class="shrink-0 text-on-dark/64 hover:text-on-dark" aria-label="{{ __('Dismiss') }}">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    {{-- One-shot toast flashed across a redirect (e.g. the checkout empty-selection guard). --}}
    @if (session()->has('toast'))
        <div x-data x-init="$store.toasts.push(@js(session('toast')['message'] ?? ''), @js(session('toast')))" class="hidden"></div>
    @endif

    {{-- Global overlays --}}
    <livewire:storefront.layout.search-overlay />
    <livewire:storefront.layout.mini-cart />
    @if (config('services.concierge.enabled', true) && ! $bareChrome)
        <livewire:storefront.shop-assistant />
    @endif

    @stack('scripts')
</body>
</html>
