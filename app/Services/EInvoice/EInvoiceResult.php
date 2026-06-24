<?php

namespace App\Services\EInvoice;

use App\Enums\EInvoiceStatus;

/** Outcome of a provider submission — persisted onto the EInvoiceDocument. */
final class EInvoiceResult
{
    public function __construct(
        public readonly EInvoiceStatus $status,
        public readonly ?string $submissionUid = null,
        public readonly ?string $uin = null,
        public readonly ?string $validationUrl = null,
        public readonly ?string $error = null,
    ) {}

    public static function pending(?string $note = null): self
    {
        return new self(EInvoiceStatus::Pending, error: $note);
    }

    public static function failed(string $error): self
    {
        return new self(EInvoiceStatus::Failed, error: $error);
    }
}
