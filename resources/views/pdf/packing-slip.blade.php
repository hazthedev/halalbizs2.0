<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1A1714; margin: 32px; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        h1 { font-size: 20px; margin: 0 0 2px; color: #1A1714; }
        .muted { color: #5C544B; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; color: #5C544B; border-bottom: 1px solid #D2C9B8; padding: 6px 4px; }
        td { padding: 10px 4px; border-bottom: 1px solid #E7E1D5; vertical-align: top; }
        .num { text-align: right; }
        .qty { font-size: 16px; font-weight: bold; }
        .header-table td { border: none; padding: 0; }
        .header-rule { border: none; border-top: 2px solid #1A1714; margin: 10px 0 0; }
        .box { border: 1px solid #D2C9B8; border-radius: 6px; padding: 10px 12px; margin-top: 16px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <h1>{{ __('Packing slip') }}</h1>
                <div class="muted">HalalBizs</div>
            </td>
            <td class="num">
                <div class="mono">{{ $subOrder->sub_order_no }}</div>
                <div class="muted">{{ $subOrder->order->placed_at->format('d M Y, H:i') }}</div>
                <div class="muted">{{ __('Order') }} <span class="mono">{{ $subOrder->order->order_no }}</span></div>
            </td>
        </tr>
    </table>
    <hr class="header-rule">

    <table class="header-table" style="margin-top: 20px;">
        <tr>
            <td width="50%">
                <strong>{{ __('Ship from') }}</strong><br>
                {{ $subOrder->store->name }}<br>
                <span class="muted">{{ $subOrder->store->state }}</span>
            </td>
            <td width="50%">
                <strong>{{ __('Ship to') }}</strong><br>
                {{ $subOrder->order->shipping_address['recipient_name'] ?? '' }}<br>
                {{ $subOrder->order->shipping_address['line1'] ?? '' }}@if(!empty($subOrder->order->shipping_address['line2'])), {{ $subOrder->order->shipping_address['line2'] }}@endif<br>
                {{ $subOrder->order->shipping_address['postcode'] ?? '' }} {{ $subOrder->order->shipping_address['city'] ?? '' }}, {{ $subOrder->order->shipping_address['state'] ?? '' }}<br>
                <span class="muted">{{ $subOrder->order->shipping_address['phone'] ?? '' }}</span>
            </td>
        </tr>
    </table>

    @if ($subOrder->tracking_courier || $subOrder->awb_no)
        <div class="box">
            <strong>{{ __('Courier') }}:</strong> {{ $subOrder->tracking_courier ?? $subOrder->courier_service ?? '—' }}
            &nbsp;·&nbsp;
            <strong>{{ __('Tracking') }}:</strong> <span class="mono">{{ $subOrder->awb_no ?? $subOrder->tracking_no ?? '—' }}</span>
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>{{ __('Item') }}</th>
                <th>{{ __('Variation') }}</th>
                <th>{{ __('SKU') }}</th>
                <th class="num">{{ __('Qty') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($subOrder->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td class="muted">{{ $item->variant_label ?? '—' }}</td>
                    <td class="mono muted">{{ $item->variant->sku ?? '—' }}</td>
                    <td class="num qty">{{ $item->qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="muted" style="margin-top: 32px; font-size: 10px;">
        {{ __('No prices shown — this is a fulfilment document. The customer invoice is sent separately.') }}
    </p>
</body>
</html>
