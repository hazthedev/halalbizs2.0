<?php

use App\Enums\ActorType;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\ReturnReason;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\SubOrderStatusNotification;
use App\Services\ReturnService;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function returnServiceReason(): ReturnReason
{
    return ReturnReason::firstOrCreate(['position' => 0], ['label' => ['en' => 'Item is damaged or defective'], 'is_active' => true]);
}

function returnServiceSubOrder(SubOrderStatus $status = SubOrderStatus::Delivered): SubOrder
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $order = Order::factory()->create(['user_id' => $buyer->id]);

    return SubOrder::factory()->status($status)->create([
        'order_id' => $order->id,
        'store_id' => Store::factory()->approved()->create()->id,
    ]);
}

test('request() creates the row, attaches the photos and transitions the sub-order in one go', function () {
    Notification::fake();
    Storage::fake('public');
    $this->freezeTime();

    $subOrder = returnServiceSubOrder();
    $buyer = $subOrder->order->user;
    $reason = returnServiceReason();

    $request = app(ReturnService::class)->request($subOrder, $buyer, $reason, '  Handle snapped on first use.  ', [
        UploadedFile::fake()->image('crack.jpg'),
        UploadedFile::fake()->image('lid.jpg'),
    ]);

    expect($request->sub_order_id)->toBe($subOrder->id)
        ->and($request->status)->toBe(ReturnStatus::Requested)
        ->and($request->return_reason_id)->toBe($reason->id)
        ->and($request->description)->toBe('Handle snapped on first use.')
        ->and($request->respond_by->timestamp)->toBe(now()->addHours(48)->timestamp)
        ->and($request->getMedia('photos'))->toHaveCount(2)
        ->and($request->getMedia('photos')->first()->file_name)->toBe('return-crack.jpg')
        ->and($subOrder->refresh()->status)->toBe(SubOrderStatus::ReturnRequested);

    $history = $subOrder->statusHistories()->get()->last();
    expect($history->to_status)->toBe('return_requested')
        ->and($history->actor_type)->toBe(ActorType::Buyer)
        ->and($history->actor_id)->toBe($buyer->id);

    Notification::assertSentTo($subOrder->store->user, SubOrderStatusNotification::class);
});

test('request() refuses more than MAX_PHOTOS photos, non-images and oversized photos before writing anything', function () {
    Storage::fake('public');
    $subOrder = returnServiceSubOrder();
    $buyer = $subOrder->order->user;
    $reason = returnServiceReason();
    $service = app(ReturnService::class);

    expect(fn () => $service->request($subOrder, $buyer, $reason, null,
        collect(range(1, 6))->map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"))->all()))
        ->toThrow(InvalidArgumentException::class, 'You can attach up to 5 photos.');

    expect(fn () => $service->request($subOrder, $buyer, $reason, null, [UploadedFile::fake()->create('evil.html', 10, 'text/html')]))
        ->toThrow(InvalidArgumentException::class, 'Photos must be images');

    expect(fn () => $service->request($subOrder, $buyer, $reason, null, [UploadedFile::fake()->image('huge.jpg')->size(3000)]))
        ->toThrow(InvalidArgumentException::class, 'Each photo must be 2MB or smaller.');

    expect(ReturnRequest::count())->toBe(0)
        ->and($subOrder->fresh()->status)->toBe(SubOrderStatus::Delivered);

    $request = $service->request($subOrder, $buyer, $reason, '   ');

    expect($request->description)->toBeNull()
        ->and($request->getMedia('photos'))->toHaveCount(0);
});

test('request() refuses a stranger, an order outside the window and a second request', function () {
    $service = app(ReturnService::class);
    $reason = returnServiceReason();

    $subOrder = returnServiceSubOrder();
    expect($service->canRequest($subOrder))->toBeTrue();

    expect(fn () => $service->request($subOrder, User::factory()->create(), $reason, null))
        ->toThrow(AuthorizationException::class);

    $this->travel(8)->days(); // return_window_days default is 7
    expect($service->canRequest($subOrder->fresh()))->toBeFalse();
    expect(fn () => $service->request($subOrder->fresh(), $subOrder->order->user, $reason, null))
        ->toThrow(InvalidArgumentException::class, 'no longer eligible');
    $this->travelBack();

    $second = returnServiceSubOrder();
    $service->request($second, $second->order->user, $reason, null);

    expect(fn () => $service->request($second->fresh(), $second->order->user, $reason, null))
        ->toThrow(InvalidArgumentException::class);

    expect(ReturnRequest::count())->toBe(1)
        ->and($subOrder->fresh()->status)->toBe(SubOrderStatus::Delivered);
});
