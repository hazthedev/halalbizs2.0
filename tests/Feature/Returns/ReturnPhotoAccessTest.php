<?php

use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\ReturnReason;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\ReturnService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
 * Audit lead api-return-photos-on-public-media-disk (gate NO 2026-11-17):
 * return evidence photos live on the private disk and are served only to the
 * buyer, the seller of that sub-order's store, or an admin.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
    Storage::fake('public');
    Storage::fake('local');

    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $this->subOrder = SubOrder::factory()->status(SubOrderStatus::Delivered)->create([
        'order_id' => Order::factory()->create(['user_id' => $buyer->id])->id,
        'store_id' => Store::factory()->approved()->create()->id,
    ]);
    $reason = ReturnReason::firstOrCreate(['position' => 0], ['label' => ['en' => 'Damaged'], 'is_active' => true]);

    $this->return = app(ReturnService::class)->request($this->subOrder, $buyer, $reason, null, [UploadedFile::fake()->image('crack.jpg')]);
    $this->photo = $this->return->getMedia('photos')->first();
    $this->url = route('returns.photo', [$this->return, $this->photo]);
});

test('return photos are stored on the private disk with a server-side name', function () {
    expect($this->photo->disk)->toBe('local')
        ->and($this->photo->file_name)->toMatch('/^return-[A-Za-z0-9]{40}\.jpg$/')
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(Storage::disk('local')->exists($this->photo->getPathRelativeToRoot()))->toBeTrue();
});

test('the buyer, the seller of that order and an admin can open the photo', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    foreach ([$this->subOrder->order->user, $this->subOrder->store->user, $admin] as $viewer) {
        $this->actingAs($viewer)->get($this->url)->assertOk()->assertHeader('content-type', 'image/jpeg');
    }
});

test('a stranger, another seller and a guest cannot open the photo', function () {
    $this->get($this->url)->assertRedirect();
    $this->actingAs(User::factory()->create())->get($this->url)->assertForbidden();
    $this->actingAs(Store::factory()->approved()->create()->user)->get($this->url)->assertForbidden();
});

test('a photo id from another return is not found under this return', function () {
    $media = $this->subOrder->store->addMedia(UploadedFile::fake()->image('logo.jpg'))->toMediaCollection('logo');

    $this->actingAs($this->subOrder->order->user)->get(route('returns.photo', [$this->return, $media]))->assertNotFound();
});

test('order screens link the photo through the authorised route, never a storage URL', function () {
    $html = $this->actingAs($this->subOrder->order->user)
        ->get(route('account.orders.show', $this->subOrder))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(e($this->url))->not->toContain('/storage/'.$this->photo->id.'/');
});
