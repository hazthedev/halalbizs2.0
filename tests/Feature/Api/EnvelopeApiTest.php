<?php

use App\Http\Api\ApiResponse;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;

test('GET config has the bootstrap shape', function () {
    $this->getJson('/api/v1/config')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.min_app_version', '1.0.0')
        ->assertJsonPath('data.cod.max_order_sen', 50000)
        ->assertJsonPath('data.urls.privacy', rtrim(config('app.url'), '/').'/page/privacy')
        ->assertJsonStructure(['data' => [
            'purchasing_enabled', 'cod' => ['enabled', 'max_order_sen'], 'locales', 'currencies', 'states', 'min_app_version',
            'urls' => ['privacy', 'terms', 'seller_apply'],
        ]]);

    expect($this->getJson('/api/v1/config')->json('data.states'))->toContain('Selangor', 'Sabah');
});

test('Accept-Language picks an enabled locale and X-Display-Currency an enabled currency', function () {
    Route::middleware('api')->get('/api/v1/_probe', fn () => ApiResponse::ok([
        'locale' => app()->getLocale(),
        'currency' => session('display_currency'),
    ]));

    $this->getJson('/api/v1/_probe')
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.currency', 'MYR');

    $this->withHeaders(['Accept-Language' => 'ms-MY,ms;q=0.9', 'X-Display-Currency' => 'usd'])
        ->getJson('/api/v1/_probe')
        ->assertJsonPath('data.locale', 'ms')
        ->assertJsonPath('data.currency', 'USD');

    $this->withHeaders(['Accept-Language' => 'xx', 'X-Display-Currency' => 'XXX'])
        ->getJson('/api/v1/_probe')
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.currency', 'MYR');

    // A translated string actually changes.
    $user = User::factory()->create();
    $user->forceFill(['status' => 'suspended'])->save();

    $this->withHeaders(['Accept-Language' => 'ms'])
        ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'p'])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.email.0', 'Akaun anda telah digantung.');
});

test('verified.api answers 403 email_unverified until the email is verified', function () {
    Route::middleware(['api', 'auth:sanctum', 'verified.api'])->get('/api/v1/_verified', fn () => ApiResponse::ok(['fine' => true]));

    $unverified = User::factory()->unverified()->create();

    $this->withToken($unverified->createToken('p')->plainTextToken)->getJson('/api/v1/_verified')
        ->assertStatus(403)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error.code', 'email_unverified');

    $unverified->markEmailAsVerified();

    app('auth')->forgetGuards(); // one app per test: drop the guard's cached user
    $this->withToken($unverified->createToken('p')->plainTextToken)->getJson('/api/v1/_verified')
        ->assertOk()
        ->assertJsonPath('data.fine', true);
});

test('unknown API routes and server errors wear the envelope', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    Route::middleware('api')->get('/api/v1/_boom', fn () => throw new RuntimeException('secret detail'));

    config(['app.debug' => false]);

    $this->withoutExceptionHandling()->getJson('/api/v1/_boom');
})->throws(RuntimeException::class);

test('a server error hides its message unless app.debug is on', function () {
    Route::middleware('api')->get('/api/v1/_boom', fn () => throw new RuntimeException('secret detail'));

    config(['app.debug' => false]);
    $this->getJson('/api/v1/_boom')
        ->assertStatus(500)
        ->assertJsonPath('error.code', 'server_error')
        ->assertJsonMissing(['message' => 'secret detail']);

    config(['app.debug' => true]);
    $this->getJson('/api/v1/_boom')
        ->assertStatus(500)
        ->assertJsonPath('error.message', 'secret detail');
});

test('paginated data carries page meta', function () {
    Route::middleware('api')->get('/api/v1/_page', fn () => ApiResponse::ok(
        new LengthAwarePaginator([['id' => 1], ['id' => 2]], 7, 2, 2),
    ));

    $this->getJson('/api/v1/_page')
        ->assertOk()
        ->assertJsonPath('data.1.id', 2)
        ->assertJsonPath('meta', ['page' => 2, 'per_page' => 2, 'total' => 7, 'last_page' => 4]);
});
