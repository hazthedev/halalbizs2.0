<?php

/**
 * Phone verification over WhatsApp (Meta Cloud API), replacing the SMS stub.
 * 2FA/phone-verify logic is unchanged — only the delivery driver and its
 * config gate + abuse cap are new.
 */

use App\Livewire\Storefront\Account\Profile;
use App\Models\User;
use App\Services\OtpService;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Services\Sms\WhatsAppSender;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(fn () => test()->seed(RoleSeeder::class));

function configureWhatsApp(): void
{
    config([
        'services.whatsapp.token' => 'test-token',
        'services.whatsapp.phone_number_id' => '111222333',
        'services.whatsapp.template' => 'verification_code',
        'services.whatsapp.template_lang' => 'en',
        'services.whatsapp.version' => 'v21.0',
    ]);
}

// ── The config gate ────────────────────────────────────────────────────────

test('the SMS sender stays the log stub when WhatsApp is not configured', function () {
    config(['services.whatsapp.token' => null, 'services.whatsapp.phone_number_id' => null]);

    expect(app(SmsSender::class))->toBeInstanceOf(LogSmsSender::class);
});

test('the SMS sender becomes the WhatsApp driver once configured', function () {
    configureWhatsApp();

    expect(app(SmsSender::class))->toBeInstanceOf(WhatsAppSender::class);
});

// ── The Cloud API call ─────────────────────────────────────────────────────

test('a verification code is sent as an authentication template carrying the code', function () {
    configureWhatsApp();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.x']]], 200)]);

    app(WhatsAppSender::class)->send('0123456789', 'Your HalalBizs verification code is 246810. It expires in 10 minutes.');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($request->url(), '/111222333/messages')
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $body['to'] === '60123456789'                          // normalised, no +, no leading 0
            && $body['type'] === 'template'
            && $body['template']['name'] === 'verification_code'
            && $body['template']['components'][0]['parameters'][0]['text'] === '246810'; // the code, not the sentence
    });
});

test('a message with no code is never sent', function () {
    configureWhatsApp();
    Http::fake();

    app(WhatsAppSender::class)->send('0123456789', 'no digits here');

    Http::assertNothingSent();
});

// ── The end-to-end verify flow over the faked gateway ──────────────────────

test('the buyer verifies their phone via the WhatsApp-delivered code', function () {
    configureWhatsApp();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.x']]], 200)]);

    $user = User::factory()->create(['phone' => null, 'phone_verified_at' => null]);
    $user->assignRole('buyer');

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('verify_phone', '0123456789')
        ->call('sendPhoneCode')
        ->assertHasNoErrors();

    // Pull the code out of the outbound template parameter — same value the
    // buyer would read in WhatsApp.
    $sentCode = null;
    Http::assertSent(function ($request) use (&$sentCode) {
        $sentCode = $request->data()['template']['components'][0]['parameters'][0]['text'] ?? null;

        return true;
    });

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('phone_otp_code', $sentCode)
        ->call('confirmPhoneCode')
        ->assertHasNoErrors();

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

// ── The abuse cap (audit AL-M6) ────────────────────────────────────────────

test('phone-verify sends are capped per user', function () {
    configureWhatsApp();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'x']]], 200)]);

    $user = User::factory()->create(['phone' => null]);
    $user->assignRole('buyer');

    // Five sends allowed; OtpService's own 1/min throttle is cleared each round
    // so we're exercising the hourly abuse cap, not the per-minute one.
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::clear('otp-issue:'.OtpService::PURPOSE_PHONE_VERIFY.':'.$user->id);
        Livewire::actingAs($user)->test(Profile::class)
            ->set('verify_phone', '0123456789')->call('sendPhoneCode');
    }

    RateLimiter::clear('otp-issue:'.OtpService::PURPOSE_PHONE_VERIFY.':'.$user->id);
    Livewire::actingAs($user)->test(Profile::class)
        ->set('verify_phone', '0123456789')
        ->call('sendPhoneCode')
        ->assertHasErrors('verify_phone'); // the 6th is refused by the hourly cap
});
