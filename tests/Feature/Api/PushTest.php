<?php

use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Support\AppLinks;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\ApiPushProbeNotification;

/** A database notification shaped like the 21 real ones: message + url. */
function apiPushNotification(string $url = 'http://halalbizs2.0.test/account/orders/5'): Notification
{
    return new ApiPushProbeNotification($url);
}

/** Writes a throwaway service-account JSON with a fresh RSA key and points FCM_CREDENTIALS at it. */
function apiConfigureFcm(): void
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);

    $path = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($path, json_encode([
        'project_id' => 'halalbizs-test',
        'client_email' => 'push@halalbizs-test.iam.gserviceaccount.com',
        'private_key' => $pem,
    ]));

    config(['services.fcm.credentials' => $path]);
}

function apiFakeFcm(int $status = 200, array $body = ['name' => 'projects/halalbizs-test/messages/1']): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::response($body, $status),
    ]);
}

test('the channel is a silent no-op without credentials', function () {
    config(['services.fcm.credentials' => null]);
    Http::fake();

    $user = User::factory()->create();
    $user->deviceTokens()->create(['token' => 'fcm-1', 'platform' => 'ios']);

    app(FcmChannel::class)->send($user, apiPushNotification());

    Http::assertNothingSent();
});

test('configured, it sends one FCM v1 message per device token with the app route', function () {
    apiConfigureFcm();
    apiFakeFcm();

    $user = User::factory()->create();
    $user->deviceTokens()->create(['token' => 'fcm-1', 'platform' => 'ios']);
    $user->deviceTokens()->create(['token' => 'fcm-2', 'platform' => 'android']);

    app(FcmChannel::class)->send($user, apiPushNotification());

    Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
        && substr_count((string) $request['assertion'], '.') === 2);

    foreach (['fcm-1', 'fcm-2'] as $token) {
        Http::assertSent(fn (Request $request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/halalbizs-test/messages:send'
            && $request->hasHeader('Authorization', 'Bearer ya29.test')
            && $request['message'] === [
                'notification' => ['title' => config('app.name'), 'body' => 'Order shipped — SO-1'],
                'data' => ['url' => '/orders/5'],
                'token' => $token,
            ]);
    }

    Http::assertSentCount(3);
});

test('an UNREGISTERED answer from FCM removes that device token', function () {
    apiConfigureFcm();
    apiFakeFcm(404, ['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]]);

    $user = User::factory()->create();
    $user->deviceTokens()->create(['token' => 'fcm-dead', 'platform' => 'ios']);

    app(FcmChannel::class)->send($user, apiPushNotification());

    expect($user->deviceTokens()->count())->toBe(0);
});

test('a database notification pushes through the NotificationSent listener without editing the notification', function () {
    apiConfigureFcm();
    apiFakeFcm();

    $user = User::factory()->create();
    $user->deviceTokens()->create(['token' => 'fcm-1', 'platform' => 'ios']);

    $user->notify(apiPushNotification());

    expect($user->notifications()->count())->toBe(1);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://fcm.googleapis.com/')
        && $request['message']['token'] === 'fcm-1');
});

test('AppLinks maps the notification web URLs to app routes', function (?string $web, ?string $app) {
    expect(AppLinks::fromWebUrl($web))->toBe($app);
})->with([
    ['http://halalbizs2.0.test/account/orders/12', '/orders/12'],
    ['http://halalbizs2.0.test/account/orders', '/orders'],
    ['http://halalbizs2.0.test/seller/orders/7', '/seller/orders/7'],
    ['http://halalbizs2.0.test/account/messages?store=3', '/account/messages'],
    ['http://halalbizs2.0.test/account/notifications', '/account/notifications'],
    ['http://halalbizs2.0.test/account/coins', '/account/coins'],
    ['http://halalbizs2.0.test/p/kurma-ajwa', '/p/kurma-ajwa'],
    ['http://halalbizs2.0.test/seller/questions', null],
    ['http://halalbizs2.0.test/account#security', null],
    [null, null],
]);
