@props(['status'])

@php
// Status colors per docs/03 §6: pending → warn · shipped/processing → ink ·
// completed → emerald · cancelled/refund → danger.
$variant = match ($status) {
    \App\Enums\SubOrderStatus::PendingPayment => 'warn',
    \App\Enums\SubOrderStatus::Completed => 'sale',
    \App\Enums\SubOrderStatus::Cancelled,
    \App\Enums\SubOrderStatus::ReturnRequested,
    \App\Enums\SubOrderStatus::Returned,
    \App\Enums\SubOrderStatus::Refunded => 'danger',
    default => 'cod', // ink outline — confirmed / processing / shipped / delivered
};
@endphp

<x-ui.badge :variant="$variant" {{ $attributes }}>{{ $status->label() }}</x-ui.badge>
