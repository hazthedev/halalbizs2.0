<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $payable ? __('Payment submitted') : __('Order not payable') }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-paper p-4 text-ink antialiased">
    <div class="w-full max-w-sm rounded-[var(--radius-card)] border border-line bg-surface p-6 text-center shadow-pop">
        @if ($payable)
            <h1 class="font-display text-xl font-bold">{{ __('Payment submitted') }}</h1>
            <p class="mt-2 text-sm text-ink-soft">{{ __('Return to the HalalBizs app — it checks the payment status itself and will show your order the moment it is confirmed.') }}</p>
        @else
            <h1 class="font-display text-xl font-bold">{{ __('This order can no longer be paid here') }}</h1>
            <p class="mt-2 text-sm text-ink-soft">{{ __('It was already paid, cancelled or has expired. Open the HalalBizs app to see its status.') }}</p>
        @endif
        <p class="mt-1 text-sm text-ink-soft">{{ __('Order') }} <span class="font-mono">{{ $order->order_no }}</span></p>
        <a href="halalbizs://pay/{{ $order->order_no }}" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-emerald px-6 text-sm font-semibold text-white hover:bg-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
            {{ __('Return to the HalalBizs app') }}
        </a>
    </div>
</body>
</html>
