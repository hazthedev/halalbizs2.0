<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class E2eStoreStatus extends Command
{
    protected $signature = 'e2e:store-status {email}';

    protected $description = 'Print the store status for a user (local only)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();
        $this->line($user?->store?->status?->value ?? 'none');

        return self::SUCCESS;
    }
}
