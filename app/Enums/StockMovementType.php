<?php

namespace App\Enums;

/** Why a variant's stock changed — the forensic categories for oversell tracing. */
enum StockMovementType: string
{
    case Sale = 'sale';
    case Restock = 'restock';
    case ReturnRestock = 'return_restock';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Sale => __('Sale'),
            self::Restock => __('Restock'),
            self::ReturnRestock => __('Return restock'),
            self::Adjustment => __('Adjustment'),
        };
    }
}
