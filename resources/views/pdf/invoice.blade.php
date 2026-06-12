<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #191B1A; margin: 32px; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .muted { color: #5B615D; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; color: #5B615D; border-bottom: 1px solid #C9CEC9; padding: 6px 4px; }
        td { padding: 8px 4px; border-bottom: 1px solid #E5E7E2; vertical-align: top; }
        .num { text-align: right; }
        .totals td { border-bottom: none; padding: 3px 4px; }
        .grand { font-size: 14px; font-weight: bold; border-top: 1px solid #191B1A; }
        .header-table td { border: none; padding: 0; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <h1>HalalBizs</h1>
                <div class="muted">{{ __('Tax invoice') }}</div>
            </td>
            <td class="num">
                <div class="mono">{{ $subOrder->sub_order_no }}</div>
                <div class="muted">{{ $subOrder->order->placed_at->format('d M Y, H:i') }}</div>
                <div class="muted">{{ __('Order') }} <span class="mono">{{ $subOrder->order->order_no }}</span></div>
            </td>
        </tr>
    </table>

    <table class="header-table" style="margin-top: 24px;">
        <tr>
            <td width="50%">
                <strong>{{ __('Sold by') }}</strong><br>
                {{ $subOrder->store->name }}<br>
                <span class="muted">{{ $subOrder->store->state }}</span>
            </td>
            <td width="50%">
                <strong>{{ __('Deliver to') }}</strong><br>
                {{ $subOrder->order->shipping_address['recipient_name'] ?? '' }}<br>
                {{ $subOrder->order->shipping_address['line1'] ?? '' }}@if(!empty($subOrder->order->shipping_address['line2'])), {{ $subOrder->order->shipping_address['line2'] }}@endif<br>
                {{ $subOrder->order->shipping_address['postcode'] ?? '' }} {{ $subOrder->order->shipping_address['city'] ?? '' }}, {{ $subOrder->order->shipping_address['state'] ?? '' }}<br>
                <span class="muted">{{ $subOrder->order->shipping_address['phone'] ?? '' }}</span>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>{{ __('Item') }}</th>
                <th>{{ __('Variation') }}</th>
                <th class="num">{{ __('Unit price') }}</th>
                <th class="num">{{ __('Qty') }}</th>
                <th class="num">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($subOrder->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td class="muted">{{ $item->variant_label ?? '—' }}</td>
                    <td class="num">{{ \App\Support\Money::format($item->unit_price_sen) }}</td>
                    <td class="num">{{ $item->qty }}</td>
                    <td class="num">{{ \App\Support\Money::format($item->line_total_sen) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="width: 40%; margin-left: 60%;">
        <tr>
            <td class="muted">{{ __('Items total') }}</td>
            <td class="num">{{ \App\Support\Money::format($subOrder->items_subtotal_sen) }}</td>
        </tr>
        <tr>
            <td class="muted">{{ __('Shipping') }}</td>
            <td class="num">{{ \App\Support\Money::format($subOrder->shipping_fee_sen) }}</td>
        </tr>
        @if ($subOrder->shop_discount_sen > 0)
            <tr>
                <td class="muted">{{ __('Discount') }}</td>
                <td class="num">-{{ \App\Support\Money::format($subOrder->shop_discount_sen) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>{{ __('Total') }}</td>
            <td class="num">{{ \App\Support\Money::format($subOrder->total_sen) }}</td>
        </tr>
        <tr>
            <td class="muted">{{ __('Payment') }}</td>
            <td class="num">{{ $subOrder->order->payment_method->label() }}</td>
        </tr>
    </table>

    <p class="muted" style="margin-top: 40px; font-size: 10px;">
        {{ __('Prices are final and tax-inclusive. The platform is not the seller of record.') }}
    </p>
</body>
</html>
