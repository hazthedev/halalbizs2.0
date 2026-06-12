@props(['status'])

@php
// Store status colors per docs/03 §6: pending → warn · approved → emerald-tint ·
// suspended/rejected → danger.
$variant = match ($status) {
    \App\Enums\StoreStatus::Pending => 'warn',
    \App\Enums\StoreStatus::Approved => 'sale',
    \App\Enums\StoreStatus::Suspended,
    \App\Enums\StoreStatus::Rejected => 'danger',
};
@endphp

<x-ui.badge :variant="$variant" {{ $attributes }}>{{ $status->label() }}</x-ui.badge>
