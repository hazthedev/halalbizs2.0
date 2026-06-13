<?php

namespace App\Console\Commands;

use App\Enums\StoreStatus;
use App\Models\Address;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;

class MakeE2eUser extends Command
{
    protected $signature = 'e2e:user {email} {--pending-store} {--with-address} {--two-factor}';

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

        if ($this->option('two-factor')) {
            $user->forceFill(['two_factor_method' => 'email'])->save();
        }

        if ($this->option('with-address') && $user->addresses()->doesntExist()) {
            Address::factory()->default()->create([
                'user_id' => $user->id,
                'state' => 'Selangor',
            ]);
        }

        if ($this->option('pending-store')) {
            // Prune stale fixture applications from earlier runs — the
            // oldest-first admin queue paginates, pushing fresh ones off page 1.
            Store::where('status', StoreStatus::Pending)
                ->where('name', 'like', 'Pending Shop %')
                ->where('user_id', '!=', $user->id)
                ->get()
                ->each(function (Store $stale) {
                    $stale->documents()->delete();
                    $stale->forceDelete();
                });
        }

        if ($this->option('pending-store') && $user->store === null) {
            $store = Store::factory()->create([
                'user_id' => $user->id,
                'name' => 'Pending Shop '.now()->format('His'),
                'status' => StoreStatus::Pending,
            ]);
            $store->documents()->create(['type' => 'ssm']);
            $store->documents()->create(['type' => 'ic']);
            $this->line($store->name);
        }

        $this->info("Ready: {$user->email}");

        return self::SUCCESS;
    }
}
