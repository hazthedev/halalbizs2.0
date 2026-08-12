<?php

use App\Livewire\Storefront\Account\Profile;
use App\Models\User;
use App\Services\OtpService;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/**
 * Swap the SMS stub for a recorder so tests can read the code.
 */
function recordSms(): object
{
    $fake = new class implements SmsSender
    {
        /** @var array<int, array{phone: string, message: string}> */
        public array $messages = [];

        public function send(string $phone, string $message): void
        {
            $this->messages[] = ['phone' => $phone, 'message' => $message];
        }
    };

    app()->instance(SmsSender::class, $fake);

    return $fake;
}

function smsCode(object $sms): string
{
    preg_match('/\b(\d{6})\b/', end($sms->messages)['message'], $matches);

    return $matches[1];
}

test('verifying the SMS code sets phone_verified_at', function () {
    $sms = recordSms();

    $user = User::factory()->create(['phone' => null]);
    $user->assignRole('buyer');

    $profile = Livewire::actingAs($user)->test(Profile::class)
        ->set('verify_phone', '012-345 6789')
        ->call('sendPhoneCode')
        ->assertHasNoErrors();

    expect($user->fresh()->phone)->toBe('012-345 6789')
        ->and($user->fresh()->phone_verified_at)->toBeNull()
        ->and($sms->messages)->toHaveCount(1)
        ->and($sms->messages[0]['phone'])->toBe('012-345 6789');

    $profile->set('phone_otp_code', smsCode($sms))
        ->call('confirmPhoneCode')
        ->assertHasNoErrors();

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('the local SMS stub logs instead of sending', function () {
    Log::shouldReceive('info')
        ->once()
        ->withArgs(fn (string $message) => $message === 'SMS to 0123456789: your code is 123456');

    (new LogSmsSender)->send('0123456789', 'your code is 123456');
});

test('non-Malaysian numbers are rejected before any SMS goes out', function (string $phone) {
    $sms = recordSms();

    $user = User::factory()->create();
    $user->assignRole('buyer');

    Livewire::actingAs($user)->test(Profile::class)
        ->set('verify_phone', $phone)
        ->call('sendPhoneCode')
        ->assertHasErrors(['verify_phone']);

    expect($sms->messages)->toBeEmpty();
})->with(['+44 20 7946 0958', '03-1234 5678', 'not-a-phone']);

test('five wrong SMS codes burn the code — even the right one stops working', function () {
    $sms = recordSms();

    $user = User::factory()->create();
    $user->assignRole('buyer');

    $profile = Livewire::actingAs($user)->test(Profile::class)
        ->set('verify_phone', '0123456789')
        ->call('sendPhoneCode')
        ->assertHasNoErrors();

    $code = smsCode($sms);
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $attempt) {
        $profile->set('phone_otp_code', $wrong)
            ->call('confirmPhoneCode')
            ->assertHasErrors(['phone_otp_code']);
    }

    expect($user->otpCodes()->count())->toBe(0);

    $profile->set('phone_otp_code', $code)
        ->call('confirmPhoneCode')
        ->assertHasErrors(['phone_otp_code']);

    expect($user->fresh()->phone_verified_at)->toBeNull();
});

test('changing the phone number in account details resets verification', function () {
    $user = User::factory()->create([
        'phone' => '0123456789',
        'phone_verified_at' => now(),
    ]);
    $user->assignRole('buyer');

    Livewire::actingAs($user)->test(Profile::class)
        ->set('phone', '0198765432')
        ->call('updateProfile')
        ->assertHasNoErrors();

    expect($user->fresh()->phone)->toBe('0198765432')
        ->and($user->fresh()->phone_verified_at)->toBeNull();
});

// H-2 follow-on: OtpService had a 1-per-minute cooldown and no ceiling, so
// anyone who knew an email could send 60 codes an hour to that inbox (or SMS
// bill) indefinitely. The cap is keyed on the DESTINATION account — the person
// being pumped — not on whoever is asking.
test('OTP sends are capped per hour on the destination account', function () {
    $recorder = recordSms();
    $user = User::factory()->create(['phone' => '0123456789']);
    $otp = app(OtpService::class);

    // The 1-per-minute cooldown is a separate, deliberate limit; clearing it
    // between sends is what a patient attacker does by waiting.
    $skipCooldown = fn () => RateLimiter::clear(
        'otp-issue:'.OtpService::PURPOSE_PHONE_VERIFY.':'.$user->id
    );

    for ($i = 0; $i < 5; $i++) {
        $skipCooldown();
        expect($otp->issue($user, OtpService::PURPOSE_PHONE_VERIFY))->toBeTrue();
    }

    $skipCooldown();

    expect($otp->issue($user, OtpService::PURPOSE_PHONE_VERIFY))->toBeFalse()
        ->and($recorder->messages)->toHaveCount(5)
        // And the wait quoted back is the HOUR, not the 60s cooldown that is no
        // longer what is holding them up.
        ->and($otp->availableIn($user, OtpService::PURPOSE_PHONE_VERIFY))->toBeGreaterThan(60);
});
