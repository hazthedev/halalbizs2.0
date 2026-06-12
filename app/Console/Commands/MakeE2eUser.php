<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeE2eUser extends Command
{
    protected $signature = 'e2e:user {email}';

    protected $description = 'Create a verified buyer for Playwright journeys (local only)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Local environment only.');

            return self::FAILURE;
        }

        $user = User::firstOrCreate(
            ['email' => $this->argument('email')],
            ['name' => 'E2E User', 'password' => 'password'],
        );

        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('buyer');

        $this->info("Ready: {$user->email}");

        return self::SUCCESS;
    }
}
