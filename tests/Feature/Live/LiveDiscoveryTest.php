<?php

use App\Enums\LiveSessionStatus;
use App\Enums\ProductStatus;
use App\Livewire\Seller\LiveSessions;
use App\Livewire\Storefront\Listing;
use App\Livewire\Storefront\Live\Room;
use App\Livewire\Storefront\StorePage;
use App\Models\LiveSession;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\LiveSessionService;
use App\Support\LiveVideoUrl;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['live.enabled' => true]);
});

function discoverySession(Store $store, string $status = 'live', ?string $url = 'https://youtu.be/dQw4w9WgXcQ'): LiveSession
{
    $session = LiveSession::create([
        'store_id' => $store->id, 'title' => 'Eid sale', 'slug' => 'eid-'.uniqid(),
        'status' => LiveSessionStatus::from($status), 'video_url' => $url, 'started_at' => now(),
    ]);
    Cache::forget('live.store-ids');

    return $session;
}

$yt = 'https://www.youtube.com/embed/dQw4w9WgXcQ';
$fbEmbed = fn (string $href) => 'https://www.facebook.com/plugins/video.php?href='.rawurlencode($href).'&show_text=false';

dataset('accepted embeds', [
    'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', $yt],
    'short link' => ['https://youtu.be/dQw4w9WgXcQ?si=abc', $yt],
    'live' => ['https://www.youtube.com/live/dQw4w9WgXcQ?feature=share', $yt],
    'shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', $yt],
    'mobile live' => ['https://m.youtube.com/live/dQw4w9WgXcQ', $yt],
    'fb videos' => ['https://www.facebook.com/somepage/videos/123456789/', $fbEmbed('https://www.facebook.com/somepage/videos/123456789/')],
    'fb watch' => ['https://www.facebook.com/watch/?v=123456789', $fbEmbed('https://www.facebook.com/watch/?v=123456789')],
    'fb reel' => ['https://m.facebook.com/reel/987654321', $fbEmbed('https://m.facebook.com/reel/987654321')],
    'fb.watch' => ['https://fb.watch/abcDEF12/', $fbEmbed('https://fb.watch/abcDEF12/')],
]);

it('builds the exact embed url', function (string $in, string $out) {
    expect(LiveVideoUrl::embed($in))->toBe($out);
})->with('accepted embeds');

dataset('rejected', [
    'evil fragment' => 'https://evil.com/#youtube.com/live/dQw4w9WgXcQ',
    'lookalike suffix' => 'https://youtube.com.evil.com/live/dQw4w9WgXcQ',
    'javascript' => 'javascript:alert(1)',
    'data' => 'data:text/html,<script>1</script>',
    'fb lookalike' => 'https://facebook.com.evil.com/videos/123',
    'fb evil query' => 'https://evil.com/videos/1?u=facebook.com',
    'fb non-video page' => 'https://www.facebook.com/somepage',
    'http' => 'http://www.youtube.com/live/dQw4w9WgXcQ',
    'short id' => 'https://www.youtube.com/live/abc',
    'shopee lookalike' => 'https://shopee.com.my.evil.com/live/1',
    'shopee in query' => 'https://evil.com/?shopee.com.my',
    'tiktok userinfo' => 'https://tiktok.com@evil.com/live',
    'empty' => '',
]);

it('rejects hostile or unsupported urls', function (string $in) {
    expect(LiveVideoUrl::embed($in))->toBeNull()
        ->and(LiveVideoUrl::parse($in))->toBeNull();
})->with('rejected');

it('treats shopee, tiktok and lazada as external links, never embeds', function (string $url, string $platform) {
    expect(LiveVideoUrl::embed($url))->toBeNull()
        ->and(LiveVideoUrl::parse($url))->toBe(['platform' => $platform, 'embed' => null, 'link' => $url]);
})->with([
    ['https://live.shopee.com.my/share?session=1', 'shopee'],
    ['https://shopee.sg/live/1', 'shopee'],
    ['https://shp.ee/abc', 'shopee'],
    ['https://vt.tiktok.com/ZS1/', 'tiktok'],
    ['https://www.tiktok.com/@shop/live', 'tiktok'],
    ['https://s.lazada.com.my/s.abc', 'lazada'],
    ['https://www.lazada.com.my/live', 'lazada'],
]);

it('shows the LIVE badge on cards and store header only while the store is live', function () {
    $store = Store::factory()->approved()->create();
    $product = Product::factory()->create(['store_id' => $store->id, 'status' => ProductStatus::Live]);
    $card = fn () => Blade::render('<x-product-card :product="$p" />', ['p' => $product->load('variants', 'store')]);

    expect($card())->not->toContain('/live/');
    Livewire::test(StorePage::class, ['store' => $store])->assertDontSee('animate-pulse');

    $session = discoverySession($store);
    expect($card())->toContain(route('live.room', $session->slug));
    Livewire::test(StorePage::class, ['store' => $store])->assertSee(route('live.room', $session->slug), false);

    app(LiveSessionService::class)->end($session);
    expect($card())->not->toContain('/live/');
});

it('adds store_live to product and store api resources', function () {
    $store = Store::factory()->approved()->create();
    $product = Product::factory()->create(['store_id' => $store->id, 'status' => ProductStatus::Live]);

    $this->getJson("/api/v1/products/{$product->slug}")->assertJsonPath('data.store_live', false);
    $this->getJson("/api/v1/stores/{$store->slug}")->assertJsonPath('data.store_live', false);

    $session = discoverySession($store);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertJsonPath('data.store_live', true)->assertJsonPath('data.live_slug', $session->slug);
    $this->getJson('/api/v1/products')->assertJsonPath('data.0.store_live', true);
    $this->getJson("/api/v1/stores/{$store->slug}")
        ->assertJsonPath('data.store_live', true)->assertJsonPath('data.live_slug', $session->slug);
});

it('uses one cached query for the live store map', function () {
    $store = Store::factory()->approved()->create();
    discoverySession($store);
    $svc = app(LiveSessionService::class);
    $svc->liveStoreIds();

    DB::enableQueryLog();
    $svc->liveStoreIds();
    $svc->liveStoreIds();
    expect(DB::getQueryLog())->toBe([]);
});

it('shows the search live pill only when a session is live', function () {
    $store = Store::factory()->approved()->create();

    Livewire::test(Listing::class, ['q' => 'abc'])->assertDontSeeHtml('data-live-pill');

    discoverySession($store);
    Livewire::test(Listing::class, ['q' => 'abc'])->assertSeeHtml('data-live-pill');
});

it('lists only live sessions on /api/v1/live', function () {
    $store = Store::factory()->approved()->create();
    $live = discoverySession($store);
    discoverySession($store, 'scheduled');
    discoverySession($store, 'ended');

    $this->getJson('/api/v1/live')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', $live->slug)
        ->assertJsonPath('data.0.store_slug', $store->slug)
        ->assertJsonPath('data.0.platform', 'youtube')
        ->assertJsonPath('data.0.thumbnail', 'https://img.youtube.com/vi/dQw4w9WgXcQ/mqdefault.jpg');
});

it('rejects a bad url in the seller form and names the platform on a good one', function () {
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $seller->id]);

    Livewire::actingAs($seller)->test(LiveSessions::class)
        ->call('create')->set('title', 'T')->set('videoUrl', 'https://evil.com/#youtube.com/live/dQw4w9WgXcQ')
        ->call('save')->assertHasErrors('videoUrl');

    Livewire::actingAs($seller)->test(LiveSessions::class)
        ->call('create')->set('title', 'T')->set('videoUrl', 'https://www.tiktok.com/@a/live')
        ->assertSee('Detected: Tiktok')
        ->call('save')->assertHasNoErrors();
});

it('renders a watch-on button and no iframe for an external session', function () {
    $store = Store::factory()->approved()->create();
    $session = discoverySession($store, 'live', 'https://live.shopee.com.my/share?session=1');

    Livewire::test(Room::class, ['session' => $session])
        ->assertSee('This live is on Shopee')
        ->assertSee('Watch on Shopee')
        ->assertSeeHtml('target="_blank" rel="noopener noreferrer"')
        ->assertDontSeeHtml('<iframe');
});

it('serves one room on /api/v1/live/{slug}: youtube embed, rail of live products only', function () {
    $store = Store::factory()->approved()->create();
    $session = discoverySession($store);
    $shown = Product::factory()->for($store)->create(['status' => ProductStatus::Live]);
    $draft = Product::factory()->for($store)->create(['status' => ProductStatus::Draft]);
    $session->products()->attach([$shown->id => ['position' => 0], $draft->id => ['position' => 1]]);

    $this->getJson("/api/v1/live/{$session->slug}")->assertOk()
        ->assertJsonPath('data.title', 'Eid sale')
        ->assertJsonPath('data.store.slug', $store->slug)
        ->assertJsonPath('data.platform', 'youtube')
        ->assertJsonPath('data.embed_url', 'https://www.youtube.com/embed/dQw4w9WgXcQ')
        ->assertJsonPath('data.external_url', null)
        ->assertJsonPath('data.is_live', true)
        ->assertJsonCount(1, 'data.products')
        ->assertJsonPath('data.products.0.slug', $shown->slug);
});

it('gives an external platform a link and no embed on the room endpoint', function () {
    $session = discoverySession(Store::factory()->approved()->create(), 'live', 'https://live.shopee.com.my/share?session=1');

    $this->getJson("/api/v1/live/{$session->slug}")->assertOk()
        ->assertJsonPath('data.platform', 'shopee')
        ->assertJsonPath('data.embed_url', null)
        ->assertJsonPath('data.external_url', 'https://live.shopee.com.my/share?session=1');
});

it('mirrors the web room: ended is still served as not live, unknown slug or feature off is 404', function () {
    $ended = discoverySession(Store::factory()->approved()->create(), 'ended');

    $this->getJson("/api/v1/live/{$ended->slug}")->assertOk()->assertJsonPath('data.is_live', false);
    $this->getJson('/api/v1/live/nope')->assertNotFound();

    config(['live.enabled' => false]);
    $this->getJson("/api/v1/live/{$ended->slug}")->assertNotFound();
});
