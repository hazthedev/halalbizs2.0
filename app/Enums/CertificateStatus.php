<?php

namespace App\Enums;

/**
 * The review lifecycle of a halal certificate (audit H-6).
 *
 * A renewal is an EDIT to the same row, not a new record: new validity plus a
 * new document sends the certificate back to Pending. That keeps one record per
 * printed certificate number — which the number's unique index already demands,
 * and which is what the public register at /certificate-register looks up.
 */
enum CertificateStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending review'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
        };
    }

    /** Pill variant, matching the vocabulary the admin and seller tables use. */
    public function variant(): string
    {
        return match ($this) {
            self::Pending => 'warn',
            self::Approved => 'sale',
            self::Rejected => 'danger',
        };
    }
}
