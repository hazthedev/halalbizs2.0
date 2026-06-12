<?php

namespace App\Console\Commands;

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Console\Command;

class MakeE2eVoucher extends Command
{
    protected $signature = 'e2e:voucher {code}';

    protected $description = 'Ensure an active RM5-off platform voucher exists (local only)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            return self::FAILURE;
        }

        Voucher::updateOrCreate(
            ['scope' => VoucherScope::Platform, 'store_id' => null, 'code' => strtoupper($this->argument('code'))],
            [
                'type' => VoucherType::Fixed,
                'value_sen' => 500,
                'min_spend_sen' => 1000,
                'quota' => null,
                'per_user_limit' => 100,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addYear(),
                'is_active' => true,
            ],
        );

        $this->info('Voucher ready.');

        return self::SUCCESS;
    }
}
