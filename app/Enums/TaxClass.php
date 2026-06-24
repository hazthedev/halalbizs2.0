<?php

namespace App\Enums;

/**
 * Worldwide tax classification of a product. The class is jurisdiction-neutral;
 * the applicable rate is resolved per destination by App\Support\Tax\TaxJurisdiction
 * (e.g. Malaysia SST: Standard = Sales 10%, Reduced = Sales 5%, Service = Service 8%,
 * ServiceReduced = Service 6%). No DB enum column (CLAUDE.md) — stored as a string.
 */
enum TaxClass: string
{
    case Exempt = 'exempt';
    case Standard = 'standard';
    case Reduced = 'reduced';
    case Service = 'service';
    case ServiceReduced = 'service_reduced';
    case ZeroRated = 'zero_rated';

    public function label(): string
    {
        return match ($this) {
            self::Exempt => __('Exempt'),
            self::Standard => __('Standard-rated'),
            self::Reduced => __('Reduced-rated'),
            self::Service => __('Service'),
            self::ServiceReduced => __('Service (reduced)'),
            self::ZeroRated => __('Zero-rated'),
        };
    }
}
