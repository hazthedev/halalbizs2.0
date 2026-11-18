<?php

use App\Livewire\Seller\LiveSessions\Detail;
use App\Livewire\Seller\LiveSessions\Form;
use App\Models\LiveSession;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\LiveSessionService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seller = User::factory()->create();
    $this->seller->assignRole('seller');
    $this->store = Store::factory()->approved()->create(['user_id' => $this->seller->id]);
    $this->actingAs($this->seller);
});

test('live session list and create pages stay separate and preserve the save payload', function () {
    $this->get(route('seller.live.index'))->assertOk()->assertSee(route('seller.live.create'), false)
        ->assertDontSee('wire:submit="save"', false)->assertDontSee('wire:model="addProductId"', false);
    $this->get(route('seller.live.create'))->assertOk()->assertSee('wire:submit="save"', false)
        ->assertDontSee(__('No live sessions yet.'));
    Livewire::test(Form::class)->set('title', 'Page stream')->set('videoUrl', 'https://www.youtube.com/watch?v=abcdefghijk')
        ->set('voucherCode', 'stream10')->set('scheduledFor', '2026-11-25T10:30')->call('save')
        ->assertHasNoErrors()->assertRedirect(route('seller.live.index'));
    $session = LiveSession::sole();
    expect($session->getRawOriginal())->toMatchArray([
        'store_id' => $this->store->id, 'title' => 'Page stream', 'slug' => 'page-stream', 'status' => 'scheduled',
        'video_url' => 'https://www.youtube.com/watch?v=abcdefghijk', 'voucher_code' => 'STREAM10',
        'scheduled_for' => '2026-11-25 02:30:00', 'started_at' => null, 'ended_at' => null, 'featured_product_id' => null,
    ]);
    $this->get(route('seller.live.index'))->assertSee('Page stream')->assertSee(route('seller.live.show', $session), false)
        ->assertSee(__('Session created — add products to your rail.'));
});

test('rail management moves to a store scoped detail page and keeps its side effects', function () {
    $session = app(LiveSessionService::class)->create($this->store, ['title' => 'Rail page']);
    $foreign = app(LiveSessionService::class)->create(Store::factory()->approved()->create(), ['title' => 'Foreign']);
    $product = Product::factory()->create(['store_id' => $this->store->id]);
    $this->get(route('seller.live.show', $foreign))->assertNotFound();
    $this->get(route('seller.live.show', $session))->assertOk()->assertSee('Rail page')->assertSee('wire:model="addProductId"', false);
    $form = Livewire::test(Detail::class, ['session' => $session])->set('addProductId', $product->id)->call('addProduct')
        ->call('feature', $session->id, $product->id);
    expect($session->fresh()->featured_product_id)->toBe($product->id)->and($session->products()->count())->toBe(1);
    $form->call('removeProduct', $session->id, $product->id);
    expect($session->fresh()->featured_product_id)->toBeNull()->and($session->products()->count())->toBe(0);
    expect($session->fresh()->title)->toBe('Rail page');
});

test('live create and detail respect the feature switch', function () {
    $session = app(LiveSessionService::class)->create($this->store, ['title' => 'Disabled']);
    config(['live.enabled' => false]);
    $this->get(route('seller.live.create'))->assertNotFound();
    $this->get(route('seller.live.show', $session))->assertNotFound();
});
