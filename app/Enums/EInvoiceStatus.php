<?php

namespace App\Enums;

/** Lifecycle of an e-invoice document (provider-agnostic). */
enum EInvoiceStatus: string
{
    case Pending = 'pending';       // recorded locally, not yet accepted by a provider
    case Submitted = 'submitted';   // sent, awaiting validation
    case Valid = 'valid';           // cleared by the tax authority (has a UIN)
    case Invalid = 'invalid';       // rejected on validation
    case Cancelled = 'cancelled';   // cancelled within the allowed window
    case Failed = 'failed';         // submission error (retryable)

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Submitted => __('Submitted'),
            self::Valid => __('Validated'),
            self::Invalid => __('Rejected'),
            self::Cancelled => __('Cancelled'),
            self::Failed => __('Failed'),
        };
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Valid, self::Cancelled], true);
    }
}
