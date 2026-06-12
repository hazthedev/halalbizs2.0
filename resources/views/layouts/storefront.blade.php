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
<body class="flex min-h-screen flex-col overflow-x-clip bg-paper text-ink antialiased"
      x-data
      x-init="$store.cart.set({{ app(\App\Services\CartService::class)->itemCount(auth()->user()) }})">

    {{-- ===== Ink header ===== --}}
    <header class="sticky top-0 z-40 bg-ink" style="border-bottom: 1px solid var(--color-emerald-night);">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:gap-6">
            <a href="{{ route('home') }}" wire:navigate class="shrink-0 font-display text-xl font-bold text-paper">
                HalalBizs
            </a>

            {{-- Search field (opens overlay) --}}
            <button
                type="button"
                x-on:click="$dispatch('open-search')"
                class="flex h-10 min-w-0 flex-1 items-center gap-2 rounded-lg bg-surface px-3.5 text-sm text-ink-faint sm:max-w-xl sm:mx-auto"
            >
                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <span class="min-w-0 truncate">{{ __('Search products, stores…') }}</span>
                <kbd class="ml-auto hidden rounded border border-line px-1.5 font-mono text-[11px] sm:block">/</kbd>
            </button>

            <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                {{-- Locale switcher --}}
                <form method="POST" action="{{ route('preferences.locale') }}" class="hidden sm:block">
                    @csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'en' ? 'ms' : 'en' }}">
                    <button type="submit" class="rounded-lg px-2 py-2 text-[13px] font-medium text-paper/64 hover:text-paper" aria-label="{{ __('Switch language') }}">
                        {{ strtoupper(app()->getLocale() === 'en' ? 'BM' : 'EN') }}
                    </button>
                </form>

                {{-- Currency switcher --}}
                <form method="POST" action="{{ route('preferences.currency') }}" class="hidden sm:block" x-data>
                    @csrf
                    <select name="currency" x-on:change="$el.form.submit()"
                            class="cursor-pointer rounded-lg border-0 bg-transparent py-2 pl-2 pr-6 text-[13px] font-medium text-paper/64 hover:text-paper focus-visible:ring-2 focus-visible:ring-emerald"
                            aria-label="{{ __('Display currency') }}">
                        @foreach (app(\App\Settings\GeneralSettings::class)->display_currencies as $code)
                            <option value="{{ $code }}" class="text-ink" @selected(session('display_currency', 'MYR') === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                </form>

                {{-- Cart --}}
                <button type="button" x-on:click="$dispatch('open-mini-cart')" class="relative flex size-10 items-center justify-center rounded-lg text-paper hover:bg-paper/10" aria-label="{{ __('Cart') }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                    <span x-show="$store.cart.count > 0" x-cloak
                          x-bind:class="$store.cart.pulse ? 'scale-125' : 'scale-100'"
                          class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-emerald px-1 text-[11px] font-bold text-white transition-transform duration-150"
                          x-text="$store.cart.count"></span>
                </button>

                {{-- Account --}}
                @auth
                    <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                        <button type="button" x-on:click="open = !open" class="flex size-10 items-center justify-center rounded-lg text-paper hover:bg-paper/10" aria-label="{{ __('Account') }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.origin.top.right.duration.150ms
                             class="absolute right-0 top-12 w-56 rounded-[10px] border border-line bg-surface py-1 shadow-lg">
                            <div class="border-b border-line px-4 py-2.5">
                                <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                                <p class="truncate text-xs text-ink-soft">{{ auth()->user()->email }}</p>
                            </div>
                            <a href="{{ route('account.profile') }}" wire:navigate class="block px-4 py-2 text-sm hover:bg-paper">{{ __('My account') }}</a>
                            <a href="{{ route('account.orders') }}" wire:navigate class="block px-4 py-2 text-sm hover:bg-paper">{{ __('My orders') }}</a>
                            <a href="{{ route('account.wishlist') }}" wire:navigate class="block px-4 py-2 text-sm hover:bg-paper">{{ __('Wishlist') }}</a>
                            @if (auth()->user()->store?->isApproved())
                                <a href="{{ route('seller.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-paper">{{ __('Seller centre') }}</a>
                            @endif
                            @role('admin')
                                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-paper">{{ __('Admin panel') }}</a>
                            @endrole
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-line">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-ink-soft hover:bg-paper">{{ __('Log out') }}</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" wire:navigate class="rounded-lg px-3 py-2 text-[13px] font-semibold text-paper hover:bg-paper/10">{{ __('Log in') }}</a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Category strip --}}
    <nav class="border-b border-line bg-paper" aria-label="{{ __('Categories') }}">
        <div class="mx-auto flex max-w-7xl items-center gap-1 overflow-x-auto px-4 py-2">
            @foreach (\App\Models\Category::active()->whereNull('parent_id')->orderBy('position')->get() as $topCategory)
                <a href="{{ route('category.show', $topCategory->slug) }}" wire:navigate
                   class="shrink-0 rounded-lg px-3 py-1.5 text-[13px] font-medium text-ink-soft hover:text-ink">
                    {{ $topCategory->getTranslation('name', app()->getLocale()) }}
                </a>
            @endforeach
        </div>
    </nav>

    {{-- ===== Main ===== --}}
    <main class="flex-1">
        {{ $slot }}
    </main>

    {{-- ===== Ink footer ===== --}}
    <footer class="mt-12 bg-ink text-paper">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="font-display text-lg font-bold">HalalBizs</p>
                <p class="mt-2 text-sm text-paper/64">{{ __('Malaysia’s trusted multi-vendor marketplace.') }}</p>
            </div>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.04em] text-paper/64">{{ __('About') }}</p>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('page.show', 'about') }}" wire:navigate class="text-paper/80 hover:text-paper">{{ __('About us') }}</a></li>
                    <li><a href="{{ route('page.show', 'terms') }}" wire:navigate class="text-paper/80 hover:text-paper">{{ __('Terms & conditions') }}</a></li>
                    <li><a href="{{ route('page.show', 'privacy') }}" wire:navigate class="text-paper/80 hover:text-paper">{{ __('Privacy policy') }}</a></li>
                </ul>
            </div>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.04em] text-paper/64">{{ __('Help') }}</p>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('page.show', 'faq') }}" wire:navigate class="text-paper/80 hover:text-paper">{{ __('FAQ') }}</a></li>
                    <li><a href="{{ route('page.show', 'refund-policy') }}" wire:navigate class="text-paper/80 hover:text-paper">{{ __('Refund policy') }}</a></li>
                    <li><a href="{{ route('seller.apply') }}" wire:navigate class="text-paper/80 hover:text-paper">{{ __('Become a seller') }}</a></li>
                </ul>
            </div>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.04em] text-paper/64">{{ __('Newsletter') }}</p>
                <form method="POST" action="{{ route('newsletter.subscribe') }}" class="mt-3 flex gap-2">
                    @csrf
                    <input type="email" name="email" required placeholder="{{ __('Your email') }}"
                           class="h-10 w-full rounded-lg border-0 bg-surface px-3 text-sm text-ink placeholder:text-ink-faint">
                    <button type="submit" class="h-10 shrink-0 rounded-lg bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep">{{ __('Subscribe') }}</button>
                </form>
                @if (session('newsletter.status'))
                    <p class="mt-2 text-[13px] text-paper/80">{{ session('newsletter.status') }}</p>
                @endif
            </div>
        </div>
        <div class="border-t border-paper/10">
            <p class="mx-auto max-w-7xl px-4 py-4 text-xs text-paper/64">© {{ now()->year }} HalalBizs. {{ __('All rights reserved.') }}</p>
        </div>
    </footer>

    {{-- Toasts (ink surface, bottom-right desktop / bottom-center mobile) --}}
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4 sm:items-end" aria-live="polite">
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="translate-y-2 opacity-0"
                 x-transition:leave="transition duration-100 ease-in" x-transition:leave-end="opacity-0"
                 class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-[10px] bg-ink px-4 py-3 text-sm text-paper shadow-lg">
                <svg x-show="toast.type === 'success'" class="size-4 shrink-0 text-emerald" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                <svg x-show="toast.type === 'error'" class="size-4 shrink-0 text-danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                <span x-text="toast.message" class="flex-1"></span>
                <a x-show="toast.actionLabel && toast.actionEvent === 'view-cart'" href="{{ route('cart') }}" wire:navigate class="shrink-0 font-semibold text-emerald" x-text="toast.actionLabel"></a>
                <button type="button" x-show="toast.actionLabel && toast.actionEvent && toast.actionEvent !== 'view-cart'"
                        x-on:click="Livewire.dispatch(toast.actionEvent, toast.actionPayload ?? {}); $store.toasts.dismiss(toast.id)"
                        class="shrink-0 font-semibold text-emerald" x-text="toast.actionLabel"></button>
                <button type="button" x-on:click="$store.toasts.dismiss(toast.id)" class="shrink-0 text-paper/64 hover:text-paper" aria-label="{{ __('Dismiss') }}">
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

    @stack('scripts')
</body>
</html>
