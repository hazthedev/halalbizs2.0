<?php

use App\Jobs\SendWebhookJob;
use App\Livewire\Admin\Catalog\Categories;
use App\Livewire\Admin\Content\Banners\Form as BannersForm;
use App\Livewire\Admin\Content\HomeSections;
use App\Livewire\Admin\Content\Pages\Form as PagesForm;
use App\Livewire\Admin\Content\Theme;
use App\Livewire\Seller\Messages as SellerMessages;
use App\Livewire\Seller\Products\Form as ProductForm;
use App\Livewire\Storefront\Account\Messages as BuyerMessages;
use App\Livewire\Storefront\Help\Tickets;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Banner;
use App\Models\Category;
use App\Models\ContentVersion;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Page;
use App\Models\Product;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\ThemeAsset;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Services\ChatService;
use App\Settings\ThemeSettings;
use App\Support\SendLimit;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Production checklist rerun 2026-11-23, small blockers (HB-B3B):
 * SEC-32 product attribute ids are validated, never intval-cast.
 * SEC-38 chat messages and support tickets are rate limited per user.
 * SEC-41 admin uploads are stored under a safe name with the detected extension.
 * SEC-46 the webhook URL guard checks IPv4 and IPv6, refuses the unresolvable,
 *        and delivery never follows a redirect.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
});

function b3bSeller(): User
{
    $user = User::factory()->create();
    $user->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

// ── SEC-32 ───────────────────────────────────────────────────────────────

function b3bFacetCategory(): array
{
    $category = Category::factory()->create();
    $colour = Attribute::create(['name' => ['en' => 'Colour'], 'slug' => 'colour-'.uniqid(), 'is_filterable' => true]);
    $red = AttributeValue::create(['attribute_id' => $colour->id, 'value' => ['en' => 'Red']]);
    $category->attributes()->attach($colour->id);

    return [$category, $red];
}

function b3bProductForm(User $seller, Category $category)
{
    return Livewire::actingAs($seller)->test(ProductForm::class)
        ->set('name.en', 'Dates')
        ->set('categoryTop', $category->id)
        ->set('price', '19.90')
        ->set('stock', '5');
}

test('SEC-32: a bad attribute id shape is rejected and nothing is saved', function (mixed $bad) {
    $seller = b3bSeller();
    [$category, $red] = b3bFacetCategory();

    b3bProductForm($seller, $category)
        ->set('attributeValueIds', [(string) $red->id, $bad])
        ->call('saveDraft')
        ->assertHasErrors('attributeValueIds.1');

    expect(Product::query()->where('store_id', $seller->store->id)->exists())->toBeFalse();
})->with([
    'text' => 'abc',
    'nested array' => [[1]],
    'unknown id' => 99999999,
    'negative' => -1,
]);

test('SEC-32: valid attribute ids from the picker (strings) still save', function () {
    $seller = b3bSeller();
    [$category, $red] = b3bFacetCategory();

    b3bProductForm($seller, $category)
        ->set('attributeValueIds', [(string) $red->id])
        ->call('saveDraft')
        ->assertHasNoErrors();

    $product = Product::query()->where('store_id', $seller->store->id)->sole();
    expect($product->attributeValues->pluck('id')->all())->toBe([$red->id]);
});

// ── SEC-38 ───────────────────────────────────────────────────────────────

test('SEC-38: a buyer gets 10 messages a minute per conversation, then a friendly error', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $store = Store::find(b3bSeller()->store->id);
    $conversation = app(ChatService::class)->openConversation($buyer, $store);
    $other = app(ChatService::class)->openConversation($buyer, Store::find(b3bSeller()->store->id));

    $screen = Livewire::actingAs($buyer)->test(BuyerMessages::class)->call('openConversation', $conversation->id);

    foreach (range(1, 10) as $n) {
        $screen->set('body', "Message {$n}")->call('send')->assertHasNoErrors();
    }

    $screen->set('body', 'Message 11')->call('send')->assertHasErrors('body');

    expect(Message::where('conversation_id', $conversation->id)->count())->toBe(10);

    // Another conversation has its own allowance.
    $screen->call('openConversation', $other->id)->set('body', 'Hello other shop')->call('send')->assertHasNoErrors();
    expect(Message::where('conversation_id', $other->id)->count())->toBe(1);
});

test('SEC-38: a seller gets 10 messages a minute per conversation, then a friendly error', function () {
    $seller = b3bSeller();
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $conversation = app(ChatService::class)->openConversation($buyer, $seller->store);

    $screen = Livewire::actingAs($seller)->test(SellerMessages::class)->call('openConversation', $conversation->id);

    foreach (range(1, 10) as $n) {
        $screen->set('body', "Reply {$n}")->call('send')->assertHasNoErrors();
    }

    $screen->set('body', 'Reply 11')->call('send')->assertHasErrors('body');

    expect(Message::where('conversation_id', $conversation->id)->count())->toBe(10);
});

test('SEC-38: a user opens at most 5 support tickets an hour', function () {
    $user = User::factory()->create();
    $screen = Livewire::actingAs($user)->test(Tickets::class);

    foreach (range(1, 5) as $n) {
        $screen->call('startTicket')
            ->set('subject', "Order MP12{$n} never arrived")
            ->set('body', 'It was marked delivered three days ago but nothing came. Please help.')
            ->call('create')
            ->assertHasNoErrors();
    }

    $screen->call('startTicket')
        ->set('subject', 'Order MP126 never arrived')
        ->set('body', 'It was marked delivered three days ago but nothing came. Please help.')
        ->call('create')
        ->assertHasErrors('subject');

    expect(SupportTicket::where('user_id', $user->id)->count())->toBe(5);
});

test('SEC-38: the limits live in config', function () {
    expect(config('limits.chat_messages_per_minute'))->toBe(10)
        ->and(config('limits.support_tickets_per_hour'))->toBe(5);
});

// ── SEC-41 ───────────────────────────────────────────────────────────────

/**
 * A real JPEG under a client name with another extension. (".html" itself
 * cannot be used here: Livewire's preview throws on it after the save.)
 */
function b3bMisnamedJpeg(int $width, int $height): UploadedFile
{
    $jpeg = UploadedFile::fake()->image('x.jpg', $width, $height);

    // Under tests Livewire takes the type from the fake's name unless one is
    // set; on a real request it is detected from the bytes, as here.
    return UploadedFile::fake()->createWithContent('evil page.webp', file_get_contents($jpeg->getRealPath()))->mimeType('image/jpeg');
}

function b3bDraftFile(string $collection): string
{
    return ContentVersion::query()->where('status', 'draft')->latest('id')->firstOrFail()->getFirstMedia($collection)->file_name;
}

test('SEC-41: a category image is stored with the detected extension', function () {
    Storage::fake('public');
    $category = Category::factory()->create();

    Livewire::actingAs(contentPublisher())->test(Categories::class)
        ->call('edit', $category->id)
        ->set('image', b3bMisnamedJpeg(800, 800))
        ->call('save')
        ->assertHasNoErrors();

    expect($category->fresh()->getFirstMedia('image')->file_name)->toBe('evil-page.jpg');
});

test('SEC-41: a banner image is stored with the detected extension', function () {
    Storage::fake('public');
    $banner = Banner::create(['title' => ['en' => 'Sale'], 'position' => 1, 'is_active' => true]);

    Livewire::actingAs(contentPublisher())->test(BannersForm::class, ['banner' => $banner->id])
        ->set('image', b3bMisnamedJpeg(1500, 500))
        ->call('save')
        ->assertHasNoErrors();

    expect(b3bDraftFile('image'))->toBe('evil-page.jpg');
});

test('SEC-41: a picture tile is stored with the detected extension', function () {
    Storage::fake('public');

    Livewire::actingAs(contentPublisher())->test(HomeSections::class)
        ->set('addType', 'picture_tiles')
        ->call('addSection')
        ->set('tileImages.1', b3bMisnamedJpeg(800, 800))
        ->set('tileLabels.1.en', 'Dates')
        ->set('tileImages.2', UploadedFile::fake()->image('gifts.jpg', 800, 800))
        ->set('tileLabels.2.en', 'Gifts')
        ->call('save')
        ->assertHasNoErrors();

    expect(b3bDraftFile('tile_1'))->toBe('evil-page.jpg');
});

test('SEC-41: a page share picture is stored with the detected extension', function () {
    Storage::fake('public');
    $page = Page::create(['slug' => 'shipping', 'title' => ['en' => 'Shipping guide'], 'body' => ['en' => '<p>We ship nationwide from Kuala Lumpur within two working days.</p>'], 'is_active' => true]);

    Livewire::actingAs(contentPublisher())->test(PagesForm::class, ['page' => $page->id])
        ->set('shareImage', b3bMisnamedJpeg(1200, 630))
        ->call('save')
        ->assertHasNoErrors();

    expect(b3bDraftFile('share_image'))->toBe('evil-page.jpg');
});

test('SEC-41: a theme hero picture is stored with the detected extension', function () {
    Storage::fake('public');
    $settings = app(ThemeSettings::class);
    $settings->hero_image_enabled = true;
    $settings->save();

    Livewire::actingAs(contentPublisher())->test(Theme::class)
        ->set('heroImage', b3bMisnamedJpeg(1600, 400))
        ->call('save')
        ->assertHasNoErrors();

    expect(ThemeAsset::hero()->draft->getFirstMedia('image')->file_name)->toBe('evil-page.jpg');
});

// ── SEC-46 ───────────────────────────────────────────────────────────────

test('SEC-46: private, loopback, link-local and reserved targets are refused, IPv4 and IPv6', function (string $url) {
    fakeHostResolver([]);

    expect(WebhookSubscription::isUrlSafe($url))->toBeFalse();
})->with([
    'https://127.0.0.1/x',
    'https://10.1.2.3/x',
    'https://169.254.169.254/latest/meta-data/',
    'https://100.64.0.1/x',
    'https://[::1]/x',
    'https://[fd00::1]/x',
    'https://[fe80::1]/x',
    'https://[::ffff:127.0.0.1]/x',
    'https://[::ffff:7f00:1]/x',
    'https://0.0.0.0/x',
    'https://[fc00::1]/x',
    'https://[::]/x',
]);

test('SEC-46: a hostname answering an IPv4-mapped loopback is refused', function () {
    fakeHostResolver(['mapped.example' => ['::ffff:127.0.0.1']]);

    expect(WebhookSubscription::isUrlSafe('https://mapped.example/hook'))->toBeFalse();
});

test('SEC-46: an unresolvable host sends nothing and is retried; a private answer is not', function () {
    Http::fake();
    fakeHostResolver(['private.example' => ['10.0.0.5']]);

    $job = fn (string $url) => new SendWebhookJob(1, $url, 's', 'order.paid', ['x' => 1]);

    expect(fn () => $job('https://nowhere.invalid/hook')->handle())
        ->toThrow(RuntimeException::class, 'did not resolve');

    $job('https://private.example/hook')->handle(); // fails at once, no throw to retry

    Http::assertNothingSent();
});

test('SEC-46: a hostname is refused when any A or AAAA answer is private', function () {
    fakeHostResolver(['mixed.example' => ['93.184.215.14', 'fd00::1'], 'v6only.example' => ['::1']]);

    expect(WebhookSubscription::isUrlSafe('https://mixed.example/hook'))->toBeFalse()
        ->and(WebhookSubscription::isUrlSafe('https://v6only.example/hook'))->toBeFalse();
});

test('SEC-46: an unresolvable hostname is unsafe', function () {
    fakeHostResolver([]);

    expect(WebhookSubscription::isUrlSafe('https://nowhere.invalid/hook'))->toBeFalse();
});

test('SEC-46: a public hostname and public literal IPs are allowed', function () {
    fakeHostResolver(['hooks.example' => ['93.184.215.14', '2606:2800:21f:cb07:6820:80da:af6b:8b2c']]);

    expect(WebhookSubscription::isUrlSafe('https://hooks.example/hook'))->toBeTrue()
        ->and(WebhookSubscription::isUrlSafe('https://8.8.8.8/x'))->toBeTrue()
        ->and(WebhookSubscription::isUrlSafe('https://[2606:4700:4700::1111]/x'))->toBeTrue();
});

test('SEC-46: a delivery answered with a redirect is not followed and not recorded as delivered', function () {
    fakeHostResolver(['hooks.example' => ['93.184.215.14']]);
    Http::fake([
        'hooks.example/*' => Http::response('', 302, ['Location' => 'https://169.254.169.254/latest/meta-data/']),
        '*' => Http::response('metadata', 200),
    ]);
    $subscription = WebhookSubscription::create(['url' => 'https://hooks.example/paid', 'secret' => 's', 'events' => ['order.paid'], 'is_active' => true]);

    try {
        SendWebhookJob::dispatchSync($subscription->id, $subscription->url, $subscription->secret, 'order.paid', ['x' => 1], 'order.paid:B3B');
    } catch (Throwable) {
        // A refused delivery may surface as a job failure; what matters is below.
    }

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
    expect(WebhookDelivery::query()->where('dedupe_key', 'order.paid:B3B')->exists())->toBeFalse();
});

// ── HB-G178 ──────────────────────────────────────────────────────────────

test('HB-G178: www is never read as a store slug, so www serves the home page', function () {
    expect(config('app.store_subdomain_base'))->not->toBeEmpty(); // else the route is not registered and this proves nothing

    $this->get('http://www.'.config('app.store_subdomain_base').'/')->assertOk();
});

test('HB-G178: a store slug that only starts with www still resolves on its subdomain', function () {
    $store = Store::factory()->approved()->create(['name' => 'Www Kedai']);

    expect($store->slug)->toStartWith('www');

    $this->get('http://'.$store->slug.'.'.config('app.store_subdomain_base'))
        ->assertOk()
        ->assertSee('Www Kedai');
});

test('HB-G178 / ENV-11: .htaccess sends www and plain http on the live host to https, before the front controller', function () {
    $rules = file_get_contents(public_path('.htaccess'));

    $www = strpos($rules, 'RewriteCond %{HTTP_HOST} ^www\.halalbizs\.com(:\d+)?$ [NC]');
    $https = strpos($rules, 'RewriteCond %{HTTP_HOST} ^halalbizs\.com(:\d+)?$ [NC]');
    $front = strpos($rules, 'RewriteRule ^ index.php [L]');
    $slash = strpos($rules, 'RewriteRule ^ %1 [L,R=301]');

    expect($www)->toBeInt()->and($https)->toBeInt()
        ->and($www)->toBeLessThan($https)
        ->and($https)->toBeLessThan($slash)
        ->and($slash)->toBeLessThan($front)
        ->and($rules)->toContain('RewriteCond %{HTTPS} !=on')
        ->and($rules)->toContain('RewriteCond %{HTTP:X-Forwarded-Proto} !=https [NC]')
        ->and(substr_count($rules, 'RewriteRule ^ https://halalbizs.com%{REQUEST_URI} [L,R=301]'))->toBe(2)
        ->and(substr_count($rules, 'RewriteCond %{REQUEST_URI} !^/\\.well-known/'))->toBe(2);
});

// ── HB-B5A follow-ups ────────────────────────────────────────────────────

test('HB-G238: more than 100 attribute ids is refused before any per-id lookup', function () {
    $seller = b3bSeller();
    [$category] = b3bFacetCategory();
    $lookups = 0;
    DB::listen(function ($query) use (&$lookups) {
        if (str_contains($query->sql, 'attribute_values') && str_contains($query->sql, 'count(*)')) {
            $lookups++;
        }
    });

    b3bProductForm($seller, $category)
        ->set('attributeValueIds', range(1, 101))
        ->call('saveDraft')
        ->assertHasErrors('attributeValueIds');

    expect($lookups)->toBe(0)
        ->and(Product::query()->where('store_id', $seller->store->id)->exists())->toBeFalse();
});

test('HB-G239: generating copy keeps the errors already on the form', function () {
    $seller = b3bSeller();
    [$category, $red] = b3bFacetCategory();

    $form = b3bProductForm($seller, $category)
        ->set('price', '')
        ->call('saveDraft')
        ->assertHasErrors('price');

    // Valid attributes: copy is generated, the price error stays.
    $form->set('attributeValueIds', [(string) $red->id])
        ->call('generateCopy')
        ->assertHasErrors('price')
        ->assertSet('description.en', fn ($value) => str_contains((string) $value, 'Dates'));

    // A bad attribute id adds its own error, the price error still stays.
    $form->set('attributeValueIds', ['abc'])
        ->call('generateCopy')
        ->assertHasErrors(['price', 'attributeValueIds.0']);
});

test('HB-G240: a chat message that fails to save does not use up the allowance', function (string $side) {
    $seller = b3bSeller();
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $conversation = app(ChatService::class)->openConversation($buyer, $seller->store);
    [$actor, $screenClass] = $side === 'buyer' ? [$buyer, BuyerMessages::class] : [$seller, SellerMessages::class];

    $failNext = true;
    Message::creating(function () use (&$failNext) {
        if ($failNext) {
            $failNext = false;
            throw new RuntimeException('database went away');
        }
    });

    expect(fn () => Livewire::actingAs($actor)->test($screenClass)->call('openConversation', $conversation->id)
        ->set('body', 'Lost message')->call('send'))->toThrow(RuntimeException::class);

    $screen = Livewire::actingAs($actor)->test($screenClass)->call('openConversation', $conversation->id);

    foreach (range(1, 10) as $n) {
        $screen->set('body', "Message {$n}")->call('send')->assertHasNoErrors();
    }

    expect(Message::where('conversation_id', $conversation->id)->count())->toBe(10);
})->with(['buyer', 'seller']);

test('HB-G240: a support ticket that fails to save does not use up the allowance', function () {
    $user = User::factory()->create();

    $failNext = true;
    SupportTicket::creating(function () use (&$failNext) {
        if ($failNext) {
            $failNext = false;
            throw new RuntimeException('database went away');
        }
    });

    $open = fn (int $n) => Livewire::actingAs($user)->test(Tickets::class)->call('startTicket')
        ->set('subject', "Order MP12{$n} never arrived")
        ->set('body', 'It was marked delivered three days ago but nothing came. Please help.')
        ->call('create');

    expect(fn () => $open(0))->toThrow(RuntimeException::class);

    foreach (range(1, 5) as $n) {
        $open($n)->assertHasNoErrors();
    }

    expect(SupportTicket::where('user_id', $user->id)->count())->toBe(5);
});

test('HB-G243: multicast and NAT64 targets are refused', function (string $url) {
    fakeHostResolver(['nat64.example' => ['64:ff9b::808:808'], 'multicast.example' => ['239.255.255.250']]);

    expect(WebhookSubscription::isUrlSafe($url))->toBeFalse();
})->with([
    'https://224.0.0.1/x',
    'https://239.255.255.250/x',
    'https://[ff02::1]/x',
    'https://[ff0e::1]/x',
    'https://[64:ff9b::a00:5]/x',
    'https://[64:ff9b::808:808]/x',
    'https://[64:ff9b:1::a00:5]/x',
    'https://[fec0::1]/x',
    'https://nat64.example/x',
    'https://multicast.example/x',
]);

test('HB-G243: delivery connects to the address that was checked, not a fresh DNS answer', function () {
    fakeHostResolver(['hooks.example' => ['93.184.215.14', '2606:2800:21f:cb07:6820:80da:af6b:8b2c']]);
    $pinned = null;
    Http::fake(function ($request, array $options) use (&$pinned) {
        $pinned = $options['curl'][CURLOPT_RESOLVE] ?? null;

        return Http::response('ok', 200);
    });

    (new SendWebhookJob(1, 'https://hooks.example/paid', 's', 'order.paid', ['x' => 1]))->handle();
    expect($pinned)->toBe(['hooks.example:443:93.184.215.14,[2606:2800:21f:cb07:6820:80da:af6b:8b2c]']);

    (new SendWebhookJob(1, 'http://hooks.example:8080/paid', 's', 'order.paid', ['x' => 1]))->handle();
    expect($pinned)->toBe(['hooks.example:8080:93.184.215.14,[2606:2800:21f:cb07:6820:80da:af6b:8b2c]']);
});

test('HB-G240: the count changes only under the send-limit lock, so two sends cannot read the same count', function () {
    // The file cache store (live) increments as a read then a write. While
    // another request holds the lock, this send waits, then is refused.
    $other = Cache::lock('send-limit:chat-send:1:1', 5);
    expect($other->get())->toBeTrue();
    $saved = false;

    expect(SendLimit::attempt('chat-send:1:1', 10, 60, function () use (&$saved) {
        $saved = true;
    }))->toBeFalse()->and($saved)->toBeFalse();

    $other->release();

    expect(SendLimit::attempt('chat-send:1:1', 10, 60, function () use (&$saved) {
        $saved = true;
    }))->toBeTrue()->and($saved)->toBeTrue();
});
