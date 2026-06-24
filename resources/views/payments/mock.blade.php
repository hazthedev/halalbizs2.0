<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Simulated payment') }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-paper p-4 text-ink antialiased">
    <div class="w-full max-w-sm rounded-[var(--radius-card)] border border-line bg-surface p-6 text-center shadow-pop">
        <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-brass">🧪 {{ __('Payment simulator') }}</p>
        <h1 class="mt-2 font-display text-xl font-bold">{{ __('No live gateway configured') }}</h1>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Order') }} <span class="font-mono">{{ $order->order_no }}</span></p>
        <p class="mt-4 text-2xl font-bold tnum">@money($order->grand_total_sen)</p>
        <p class="mt-1 text-[13px] text-ink-faint">{{ __('This is a stand-in for iPay88 — no real charge is made.') }}</p>

        <form method="POST" action="{{ route('payments.ipay88.mock', ['order' => $order->order_no, 'result' => 'success']) }}" class="mt-6">
            @csrf
            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-emerald px-6 text-sm font-semibold text-white hover:bg-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                {{ __('Simulate successful payment') }}
            </button>
        </form>
        <form method="POST" action="{{ route('payments.ipay88.mock', ['order' => $order->order_no, 'result' => 'fail']) }}" class="mt-2">
            @csrf
            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-line-strong px-6 text-sm font-medium text-ink-soft hover:border-ink focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Simulate failure') }}
            </button>
        </form>
        <a href="{{ route('checkout') }}" class="mt-3 inline-block min-h-11 text-[13px] leading-[44px] text-ink-soft hover:text-ink">{{ __('Cancel') }}</a>
    </div>
</body>
</html>
