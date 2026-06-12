<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Confirming payment…') }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-paper text-ink antialiased">
    <div class="max-w-md px-4 text-center" id="panel">
        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-tint">
            <svg class="size-7 animate-spin text-emerald" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/></svg>
        </div>
        <h1 class="mt-4 font-display text-2xl font-bold">{{ __('Confirming your payment…') }}</h1>
        <p class="mt-2 text-sm text-ink-soft">
            {{ __('Order') }} <span class="font-mono">{{ $order->order_no }}</span> —
            {{ __('this usually takes a few seconds. Don’t close this page.') }}
        </p>
        <p class="mt-6 hidden text-sm text-ink-soft" id="still-confirming">
            {{ __('Still confirming. You can safely check back later from') }}
            <a href="{{ route('account.orders') }}" class="font-semibold text-emerald">{{ __('My orders') }}</a> —
            {{ __('we’ll email you the moment it’s confirmed.') }}
        </p>
    </div>

    <script>
        // Poll order state every 2s, give up after 60s (docs/06 §D2).
        const started = Date.now();
        const poll = setInterval(async () => {
            try {
                const res = await fetch('{{ route('payments.ipay88.status', $order) }}', { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (data.paid) {
                    clearInterval(poll);
                    window.location = '{{ route('checkout.success', $order) }}';
                }
            } catch (e) { /* keep polling */ }

            if (Date.now() - started > 60000) {
                clearInterval(poll);
                document.getElementById('still-confirming').classList.remove('hidden');
            }
        }, 2000);
    </script>
</body>
</html>
