<?php

use App\Enums\CommissionBasis;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Whether commission is charged on the list price or on what the seller was
 * actually paid (Haze's call 2026-07-27 — he wants both available in the panel).
 *
 * Defaults to NET, deliberately, and this IS a behaviour change either way —
 * the old code was neither cleanly gross nor net. It charged on
 * items_subtotal_sen, which is built AFTER a flash-sale price cut but BEFORE a
 * seller's shop voucher. So the same RM20 given away cost the seller a
 * different fee depending on which mechanism they used.
 *
 * NET is the safer default of the two: it never charges a seller on money they
 * did not receive, and it is already how flash sales behaved. Choosing GROSS
 * from the panel raises the fee on flash-sale orders, so it is opt-in.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('commission.discount_basis', CommissionBasis::Net->value);
    }
};
