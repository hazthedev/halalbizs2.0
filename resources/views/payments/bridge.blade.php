<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Redirecting to payment…') }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-paper text-ink antialiased">
    <div class="text-center">
        <h1 class="font-display text-2xl font-bold">{{ __('Taking you to the payment page…') }}</h1>
        <p class="mt-2 text-sm text-ink-soft">{{ __('Order') }} <span class="font-mono">{{ $order->order_no }}</span></p>

        <form id="ipay88-entry" method="POST" action="{{ $entryUrl }}" class="mt-6">
            @foreach ($fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald px-6 text-sm font-semibold text-white hover:bg-emerald-deep">
                {{ __('Continue to payment') }}
            </button>
        </form>
    </div>

    <script>
        // Auto-submit; the button stays as a manual fallback (docs/06 §D1).
        window.addEventListener('load', function () {
            document.getElementById('ipay88-entry').submit();
        });
    </script>
</body>
</html>
