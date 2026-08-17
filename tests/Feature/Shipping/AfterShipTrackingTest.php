<?php

use App\Enums\ShipmentTrackingStatus;
use App\Enums\SubOrderStatus;
use App\Jobs\RegisterAfterShipTracking;
use App\Models\SubOrder;
use App\Services\AfterShipTrackingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function afterShipSubOrder(): SubOrder
{
    return SubOrder::factory()->status(SubOrderStatus::Shipped)->create([
        'tracking_courier' => 'ViettelPost',
        'tracking_no' => 'VTP123456789',
    ]);
}

/** @param array<string, mixed> $payload */
function postSignedAfterShipWebhook($test, array $payload, string $secret = 'webhook-secret')
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $signature = base64_encode(hash_hmac('sha256', $body, $secret, true));

    return $test->call(
        'POST',
        route('shipping.aftership.tracking'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AFTERSHIP_HMAC_SHA256' => $signature,
        ],
        $body,
    );
}

/** @return array<string, mixed> */
function afterShipPayload(SubOrder $subOrder, string $tag = 'InTransit', string $checkpointHash = 'checkpoint-1'): array
{
    return [
        'ts' => now()->timestamp,
        'event' => 'tracking_update',
        'event_id' => 'a2fa98d5-397a-4d4d-9208-'.$checkpointHash,
        'msg' => [
            'id' => 'aftership-123',
            'tracking_number' => $subOrder->tracking_no,
            'order_id' => $subOrder->sub_order_no,
            'tag' => $tag,
            'courier_tracking_link' => 'https://example.test/track/'.$subOrder->tracking_no,
            'checkpoints' => [[
                'hash' => $checkpointHash,
                'tag' => $tag,
                'subtag' => $tag.'_001',
                'message' => $tag === 'Delivered' ? 'Parcel delivered' : 'Parcel reached the Hanoi sorting centre',
                'checkpoint_time' => '2026-08-17T10:30:00+07:00',
                'city' => 'Hanoi',
                'country_region_name' => 'Vietnam',
            ]],
        ],
    ];
}

beforeEach(function () {
    config([
        'tracking.provider' => 'aftership',
        'tracking.aftership.api_key' => 'aftership-test-key',
        'tracking.aftership.webhook_secret' => 'webhook-secret',
        'tracking.aftership.base_url' => 'https://api.aftership.test/tracking/2026-07',
    ]);
});

test('tracking registration is inert until AfterShip is configured', function () {
    config(['tracking.provider' => 'none', 'tracking.aftership.api_key' => null]);
    Http::fake();

    expect(app(AfterShipTrackingService::class)->register(afterShipSubOrder()))->toBeFalse();

    Http::assertNothingSent();
});

test('a shipped parcel can be registered with AfterShip without sending buyer data', function () {
    $subOrder = afterShipSubOrder();

    Http::fake([
        'api.aftership.test/*' => Http::response([
            'data' => ['tracking' => [
                'id' => 'aftership-123',
                'tag' => 'InfoReceived',
                'courier_tracking_link' => 'https://viettelpost.test/VTP123456789',
            ]],
        ]),
    ]);

    expect(app(AfterShipTrackingService::class)->register($subOrder))->toBeTrue();

    Http::assertSent(function ($request) use ($subOrder) {
        return $request->url() === 'https://api.aftership.test/tracking/2026-07/trackings'
            && $request->hasHeader('as-api-key', 'aftership-test-key')
            && $request['tracking']['tracking_number'] === $subOrder->tracking_no
            && $request['tracking']['order_id'] === $subOrder->sub_order_no
            && ! array_key_exists('email', $request['tracking'])
            && ! array_key_exists('phone_number', $request['tracking']);
    });

    expect($subOrder->fresh()->tracking_provider)->toBe('aftership')
        ->and($subOrder->fresh()->tracking_provider_id)->toBe('aftership-123')
        ->and($subOrder->fresh()->tracking_status)->toBe(ShipmentTrackingStatus::InfoReceived)
        ->and($subOrder->fresh()->tracking_url)->toBe('https://viettelpost.test/VTP123456789');
});

test('a signed webhook stores checkpoints once and updates tracking metadata', function () {
    $subOrder = afterShipSubOrder();
    $payload = afterShipPayload($subOrder);

    postSignedAfterShipWebhook($this, $payload)->assertOk();
    postSignedAfterShipWebhook($this, $payload)->assertOk();

    $subOrder->refresh();

    expect($subOrder->tracking_provider_id)->toBe('aftership-123')
        ->and($subOrder->tracking_status)->toBe(ShipmentTrackingStatus::InTransit)
        ->and($subOrder->tracking_url)->toContain('VTP123456789')
        ->and($subOrder->trackingEvents)->toHaveCount(1)
        ->and($subOrder->trackingEvents->first()->location)->toBe('Hanoi, Vietnam');
});

test('a forged webhook is rejected without changing tracking data', function () {
    $subOrder = afterShipSubOrder();
    $payload = afterShipPayload($subOrder);
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        route('shipping.aftership.tracking'),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_AFTERSHIP_HMAC_SHA256' => 'wrong'],
        $body,
    )->assertUnauthorized();

    expect($subOrder->trackingEvents()->count())->toBe(0);
});

test('a delivered checkpoint completes the shipped leg through the order service', function () {
    $subOrder = afterShipSubOrder();

    postSignedAfterShipWebhook($this, afterShipPayload($subOrder, 'Delivered', 'checkpoint-delivered'))->assertOk();

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Delivered)
        ->and($subOrder->fresh()->delivered_at)->not->toBeNull()
        ->and($subOrder->fresh()->statusHistories()->latest('id')->first()->actor_type->value)->toBe('system');
});

test('the backfill command queues unregistered open shipments after activation', function () {
    Queue::fake();
    $subOrder = afterShipSubOrder();

    $this->artisan('tracking:register-open')
        ->expectsOutputToContain('Queued 1 shipment(s)')
        ->assertSuccessful();

    Queue::assertPushed(RegisterAfterShipTracking::class, fn ($job) => $job->subOrderId === $subOrder->id);
});
