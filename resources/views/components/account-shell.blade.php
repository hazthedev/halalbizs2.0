@props(['active' => 'profile', 'title' => null])

@php
$items = [
    'dashboard' => ['label' => __('Overview'), 'href' => route('account.dashboard')],
    'profile' => ['label' => __('Profile'), 'href' => route('account.profile')],
    'addresses' => ['label' => __('Addresses'), 'href' => route('account.addresses')],
    'orders' => ['label' => __('Orders'), 'href' => route('account.orders')],
];

if (config('coins.enabled', true)) {
    $items['coins'] = ['label' => __('Coins'), 'href' => route('account.coins')];
}

if (config('affiliate.enabled', true)) {
    $items['affiliate'] = ['label' => __('Creator'), 'href' => route('account.affiliate')];
}

if (config('subscriptions.enabled', true)) {
    $items['subscriptions'] = ['label' => __('Subscriptions'), 'href' => route('account.subscriptions')];
}

$items += [
    'messages' => ['label' => __('Messages'), 'href' => route('account.messages')],
    'wishlist' => ['label' => __('Wishlist'), 'href' => route('account.wishlist')],
    'notifications' => ['label' => __('Notifications'), 'href' => route('account.notifications')],
];
@endphp

<div class="mx-auto w-full max-w-7xl px-4 py-8 lg:py-12">
    <h1 class="font-display text-[28px] font-bold leading-tight">{{ $title ?? __('My account') }}</h1>

    <div class="mt-6 lg:grid lg:grid-cols-[200px_minmax(0,1fr)] lg:items-start lg:gap-10">
        {{-- Mobile: horizontal scroll tabs · Desktop: vertical left nav --}}
        <nav aria-label="{{ __('Account sections') }}"
             class="-mx-4 mb-6 flex overflow-x-auto border-b border-line px-4 lg:mx-0 lg:mb-0 lg:flex-col lg:overflow-visible lg:border-b-0 lg:px-0">
            @foreach ($items as $key => $item)
                @php($isActive = $active === $key)
                <a href="{{ $item['href'] }}" wire:navigate
                   @if ($isActive) aria-current="page" @endif
                   class="-mb-px flex min-h-11 shrink-0 items-center whitespace-nowrap border-b-2 px-3 text-sm transition-colors lg:mb-0 lg:border-b-0 lg:border-l-2 lg:px-3.5 {{ $isActive
                       ? 'border-brass font-semibold text-ink'
                       : 'border-transparent font-medium text-ink-soft hover:text-ink' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="min-w-0">{{ $slot }}</div>
    </div>
</div>
