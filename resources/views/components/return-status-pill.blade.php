@props(['status'])

@php
// Return request status colors: requested → warn (clock ticking) ·
// accepted → ink outline · disputed/escalated → danger · refunded → emerald ·
// rejected → neutral.
$variant = match ($status) {
    \App\Enums\ReturnStatus::Requested => 'warn',
    \App\Enums\ReturnStatus::Accepted => 'cod',
    \App\Enums\ReturnStatus::Disputed,
    \App\Enums\ReturnStatus::Escalated => 'danger',
    \App\Enums\ReturnStatus::Refunded => 'sale',
    \App\Enums\ReturnStatus::Rejected => 'neutral',
};
@endphp

<x-ui.badge :variant="$variant" {{ $attributes }}>{{ $status->label() }}</x-ui.badge>
