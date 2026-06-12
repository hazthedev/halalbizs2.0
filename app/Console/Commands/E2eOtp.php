<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Mints a fresh OTP code and prints it — Playwright journeys can't read
 * the mail log, so they get their challenge codes here (local only).
 */
class E2eOtp extends Command
{
    protected $signature = 'e2e:otp {email} {purpose=2fa-email}';

    protected $description = 'Mint and print an OTP code for Playwright journeys (local only)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $purpose = $this->argument('purpose');

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->otpCodes()->where('purpose', $purpose)->delete();
        $user->otpCodes()->create([
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->line($code);

        return self::SUCCESS;
    }
}
