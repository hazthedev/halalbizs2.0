<?php

namespace App\Console\Commands;

use App\Enums\StoreStatus;
use App\Models\User;
use Illuminate\Console\Command;

class ApproveSeller extends Command
{
    protected $signature = 'seller:approve {email : Email of the applicant}';

    protected $description = 'Approve a pending seller application (admin panel arrives in M7)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null || $user->store === null) {
            $this->error('No application found for that email.');

            return self::FAILURE;
        }

        $user->store->update([
            'status' => StoreStatus::Approved,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $user->assignRole('seller');

        $this->info("Approved store \"{$user->store->name}\" for {$user->email}.");

        return self::SUCCESS;
    }
}
