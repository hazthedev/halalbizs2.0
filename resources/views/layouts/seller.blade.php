<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ __('Seller Centre') }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-clip bg-paper text-ink antialiased" x-data="{ sidebarOpen: false }">

    {{-- Ink topbar --}}
    <header class="sticky top-0 z-40 border-b border-emerald-edge bg-emerald-night">
        <div class="flex h-14 items-center gap-3 px-4">
            <button type="button" class="flex size-10 items-center justify-center rounded-[var(--radius-control)] text-on-dark lg:hidden" x-on:click="sidebarOpen = !sidebarOpen" aria-label="{{ __('Menu') }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
            </button>
            <a href="{{ route('seller.dashboard') }}" wire:navigate class="flex items-center gap-2 font-display text-lg font-medium text-on-dark">
                <x-ui.star-mark :size="20" class="text-brass" />
                HalalBizs <span class="font-mono text-[length:var(--text-nano)] uppercase tracking-[var(--tracking-label-xl)] text-on-dark-faint">{{ __('Seller Centre') }}</span>
            </a>
            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('home') }}" class="rounded-[var(--radius-control)] px-3 py-2 text-[13px] font-medium text-on-dark/64 hover:text-on-dark">{{ __('View storefront') }}</a>
                <livewire:notification-bell context="seller" />
                <span class="hidden text-[13px] text-on-dark/64 sm:block">{{ auth()->user()->store?->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-[var(--radius-control)] px-3 py-2 text-[13px] font-medium text-on-dark/64 hover:text-on-dark">{{ __('Log out') }}</button>
                </form>
            </div>
        </div>
    </header>

    <div class="flex">
        {{-- Sidebar --}}
        <aside
            class="invisible fixed inset-y-0 left-0 z-30 w-60 -translate-x-full overflow-y-auto border-r border-line bg-surface pt-14 transition-[transform,visibility] duration-150 lg:visible lg:static lg:translate-x-0 lg:pt-0"
            x-bind:class="sidebarOpen ? 'translate-x-0 visible' : ''"
        >
            <nav class="space-y-0.5 p-3" aria-label="{{ __('Seller navigation') }}">
                @php
                    $links = [
                        ['route' => 'seller.dashboard', 'label' => __('Dashboard'), 'active' => request()->routeIs('seller.dashboard')],
                        ['route' => 'seller.products.index', 'label' => __('Products'), 'active' => request()->routeIs('seller.products.*')],
                        ['route' => 'seller.certificates', 'label' => __('Halal certificates'), 'active' => request()->routeIs('seller.certificates')],
                    ];
                @endphp
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}" wire:navigate
                       class="block rounded-[var(--radius-control)] px-3 py-3 text-sm font-medium lg:py-2 {{ $link['active'] ? 'bg-brass-tint text-brass-deep' : 'text-ink-soft hover:bg-paper hover:text-ink' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach

                @if (Illuminate\Support\Facades\Route::has('seller.orders.index'))
                    <a href="{{ route('seller.orders.index') }}" wire:navigate
                       class="block rounded-[var(--radius-control)] px-3 py-3 text-sm font-medium lg:py-2 {{ request()->routeIs('seller.orders.*') ? 'bg-brass-tint text-brass-deep' : 'text-ink-soft hover:bg-paper hover:text-ink' }}">
                        {{ __('Orders') }}
                    </a>
                @else
                    <span class="block cursor-not-allowed rounded-[var(--radius-control)] px-3 py-3 text-sm font-medium lg:py-2 text-ink-faint">{{ __('Orders') }} <span class="text-[11px]">({{ __('soon') }})</span></span>
                @endif

                @php
                    $sellerUnreadMessages = ($sellerNavStore = auth()->user()->store) === null ? 0 : \App\Models\Message::query()
                        ->whereNull('read_at')
                        ->where('sender_type', 'buyer')
                        ->whereIn('conversation_id', \App\Models\Conversation::query()->where('store_id', $sellerNavStore->id)->select('id'))
                        ->count();
                @endphp
                <a href="{{ route('seller.messages') }}" wire:navigate
                   class="flex items-center justify-between gap-2 rounded-[var(--radius-control)] px-3 py-3 text-sm font-medium lg:py-2 {{ request()->routeIs('seller.messages') ? 'bg-brass-tint text-brass-deep' : 'text-ink-soft hover:bg-paper hover:text-ink' }}">
                    <span>{{ __('Messages') }}</span>
                    @if ($sellerUnreadMessages > 0)
                        <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-emerald-tint px-1.5 text-[11px] font-medium text-emerald tnum">{{ $sellerUnreadMessages }}</span>
                    @endif
                </a>

                @php($sellerNavItems = [['seller.vouchers.index', __('Vouchers')]])
                @if (config('groupbuy.enabled', true))
                    @php($sellerNavItems[] = ['seller.group-buys.index', __('Group buys')])
                @endif
                @if (config('live.enabled', true))
                    @php($sellerNavItems[] = ['seller.live.index', __('Live shopping')])
                @endif
                @php($sellerNavItems = array_merge($sellerNavItems, [['seller.earnings', __('Earnings')], ['seller.reviews.index', __('Reviews')], ['seller.questions.index', __('Questions')]]))
                @foreach ($sellerNavItems as [$routeName, $label])
                    @if (Illuminate\Support\Facades\Route::has($routeName))
                        <a href="{{ route($routeName) }}" wire:navigate
                           class="block rounded-[var(--radius-control)] px-3 py-3 text-sm font-medium lg:py-2 {{ request()->routeIs(str_replace('.index', '.*', $routeName)) ? 'bg-brass-tint text-brass-deep' : 'text-ink-soft hover:bg-paper hover:text-ink' }}">
                            {{ $label }}
                        </a>
                    @else
                        <span class="block cursor-not-allowed rounded-[var(--radius-control)] px-3 py-3 text-sm font-medium lg:py-2 text-ink-faint">{{ $label }} <span class="text-[11px]">({{ __('soon') }})</span></span>
                    @endif
                @endforeach

                <a href="{{ route('seller.settings') }}" wire:navigate
                   class="block rounded-[var(--radius-control)] px-3 py-3 text-sm font-medium lg:py-2 {{ request()->routeIs('seller.settings') ? 'bg-brass-tint text-brass-deep' : 'text-ink-soft hover:bg-paper hover:text-ink' }}">
                    {{ __('Shop settings') }}
                </a>

                <a href="{{ route('help.index') }}" wire:navigate
                   class="block rounded-[var(--radius-control)] px-3 py-3 text-sm font-medium lg:py-2 text-ink-soft hover:bg-paper hover:text-ink">
                    {{ __('Get help') }}
                </a>
            </nav>
        </aside>

        {{-- Backdrop for mobile sidebar --}}
        <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-20 bg-emerald-night/40 lg:hidden" x-on:click="sidebarOpen = false"></div>

        <main class="min-h-[calc(100vh-3.5rem)] min-w-0 flex-1 p-4 lg:p-6">
            {{ $slot }}
        </main>
    </div>

    {{-- Toasts --}}
    <div class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-end gap-2 px-4" aria-live="polite">
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="-translate-y-2 opacity-0"
                 x-transition:leave="transition duration-100 ease-in" x-transition:leave-end="opacity-0"
                 class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-[var(--radius-card)] border border-brass/20 bg-emerald-night px-5 py-4 text-sm text-on-dark shadow-pop">
                <svg x-show="toast.type === 'success'" class="size-4 shrink-0 text-emerald" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                <svg x-show="toast.type === 'error'" class="size-4 shrink-0 text-danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                <span x-text="toast.message" class="flex-1"></span>
                <button type="button" x-on:click="$store.toasts.dismiss(toast.id)" class="shrink-0 text-on-dark/64 hover:text-on-dark" aria-label="{{ __('Dismiss') }}">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    @stack('scripts')
</body>
</html>
