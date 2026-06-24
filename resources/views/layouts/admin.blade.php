<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ __('Admin') }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-clip bg-paper text-ink antialiased" x-data="{ sidebarOpen: false }">

    {{-- Ink topbar --}}
    <header class="surface-girih sticky top-0 z-40 border-b border-brass/25 bg-ink">
        <div class="flex h-14 items-center gap-3 px-4">
            <button type="button" class="flex size-10 items-center justify-center rounded-[var(--radius-control)] text-paper lg:hidden" x-on:click="sidebarOpen = !sidebarOpen" aria-label="{{ __('Menu') }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
            </button>
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-center gap-2 font-display text-lg font-bold text-paper">
                <x-ui.star-mark :size="20" class="text-brass" />
                HalalBizs <span class="font-sans text-[13px] font-medium text-brass-tint/70">{{ __('Admin') }}</span>
            </a>
            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 text-[13px] font-medium text-paper/64 hover:text-paper">{{ __('View storefront') }}</a>
                <livewire:notification-bell context="admin" />
                <span class="hidden text-[13px] text-paper/64 sm:block">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg px-3 py-2 text-[13px] font-medium text-paper/64 hover:text-paper">{{ __('Log out') }}</button>
                </form>
            </div>
        </div>
    </header>

    <div class="flex">
        <aside
            class="fixed inset-y-0 left-0 z-30 w-60 -translate-x-full overflow-y-auto border-r border-line bg-surface pt-14 transition-transform duration-150 lg:static lg:translate-x-0 lg:pt-0"
            x-bind:class="sidebarOpen ? 'translate-x-0' : ''"
        >
            <nav class="space-y-4 p-3 text-sm" aria-label="{{ __('Admin navigation') }}">
                @php
                    $groups = [
                        null => [
                            ['admin.dashboard', __('Dashboard'), 'admin.dashboard'],
                        ],
                        __('Sellers') => [
                            ['admin.sellers.applications', __('Applications'), 'admin.sellers.applications'],
                            ['admin.sellers.stores', __('Stores'), 'admin.sellers.stores*'],
                        ],
                        __('Buyers') => [
                            ['admin.buyers.index', __('Buyers'), 'admin.buyers.*'],
                        ],
                        __('Catalog') => [
                            ['admin.catalog.categories', __('Categories'), 'admin.catalog.categories'],
                            ['admin.catalog.attributes', __('Attributes'), 'admin.catalog.attributes'],
                            ['admin.catalog.brands', __('Brands'), 'admin.catalog.brands'],
                            ['admin.catalog.moderation', __('Moderation'), 'admin.catalog.moderation'],
                            ['admin.catalog.reviews', __('Reviews'), 'admin.catalog.reviews'],
                        ],
                        __('Orders') => [
                            ['admin.orders.index', __('All orders'), 'admin.orders.index'],
                            ['admin.orders.returns', __('Returns'), 'admin.orders.returns'],
                            ['admin.payments.index', __('Payments'), 'admin.payments.*'],
                            ['admin.subscriptions.index', __('Subscriptions'), 'admin.subscriptions.*'],
                        ],
                        __('Finance') => [
                            ['admin.finance.commission', __('Commission'), 'admin.finance.commission'],
                            ['admin.finance.payouts', __('Payouts'), 'admin.finance.payouts'],
                            ['admin.finance.boosts', __('Boosts'), 'admin.finance.boosts'],
                            ['admin.coins.index', __('Loyalty Coins'), 'admin.coins.*'],
                            ['admin.affiliates.index', __('Affiliates'), 'admin.affiliates.*'],
                        ],
                        __('Content') => [
                            ['admin.content.banners', __('Banners'), 'admin.content.banners'],
                            ['admin.content.home-sections', __('Home sections'), 'admin.content.home-sections'],
                            ['admin.content.pages', __('Pages'), 'admin.content.pages'],
                            ['admin.content.vouchers', __('Vouchers'), 'admin.content.vouchers'],
                            ['admin.content.theme', __('Theme'), 'admin.content.theme'],
                            ['admin.live.index', __('Live shopping'), 'admin.live.*'],
                        ],
                        __('Support') => [
                            ['admin.support.articles', __('Help articles'), 'admin.support.articles'],
                            ['admin.support.tickets', __('Tickets'), 'admin.support.tickets'],
                        ],
                        __('System') => [
                            ['admin.localization', __('Localization'), 'admin.localization'],
                            ['admin.system.search', __('Search insights'), 'admin.system.search'],
                            ['admin.system.settings', __('Settings'), 'admin.system.settings'],
                            ['admin.system.staff', __('Staff & roles'), 'admin.system.staff'],
                            ['admin.system.audit', __('Audit log'), 'admin.system.audit'],
                        ],
                    ];
                @endphp

                @foreach ($groups as $heading => $links)
                    <div>
                        @if ($heading)
                            <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-brass-deep/70">{{ $heading }}</p>
                        @endif
                        <div class="space-y-0.5">
                            @foreach ($links as [$routeName, $label, $activePattern])
                                @if (Illuminate\Support\Facades\Route::has($routeName))
                                    <a href="{{ route($routeName) }}" wire:navigate
                                       class="block rounded-lg px-3 py-2 font-medium {{ request()->routeIs($activePattern) ? 'bg-brass-tint text-brass-deep' : 'text-ink-soft hover:bg-paper hover:text-ink' }}">
                                        {{ $label }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
        </aside>

        <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-20 bg-ink/40 lg:hidden" x-on:click="sidebarOpen = false"></div>

        <main class="min-h-[calc(100vh-3.5rem)] min-w-0 flex-1 p-4 lg:p-6">
            {{ $slot }}
        </main>
    </div>

    {{-- Toasts --}}
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4 sm:items-end" aria-live="polite">
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="translate-y-2 opacity-0"
                 x-transition:leave="transition duration-100 ease-in" x-transition:leave-end="opacity-0"
                 class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-[var(--radius-card)] border border-brass/20 bg-ink px-4 py-3 text-sm text-paper shadow-pop">
                <svg x-show="toast.type === 'success'" class="size-4 shrink-0 text-emerald" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                <svg x-show="toast.type === 'error'" class="size-4 shrink-0 text-danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                <span x-text="toast.message" class="flex-1"></span>
                <button type="button" x-on:click="$store.toasts.dismiss(toast.id)" class="shrink-0 text-paper/64 hover:text-paper" aria-label="{{ __('Dismiss') }}">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    @stack('scripts')
</body>
</html>
