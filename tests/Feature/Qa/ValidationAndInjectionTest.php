<?php

/**
 * QA sweep — input validation, stored XSS, uploads, search injection.
 *
 * Every test is written to FAIL if the defect it names is present. A test that
 * passes is recorded as "verified correct", never as a finding.
 *
 * Runs against MariaDB (hbiz2_t6): the JSON/LIKE collation tests cannot fail
 * on SQLite.
 */

use App\Enums\ProductStatus;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Content\Pages;
use App\Livewire\Admin\Support\Tickets as AdminTickets;
use App\Livewire\Seller\Orders\Detail;
use App\Livewire\Seller\Products\Form;
use App\Livewire\Seller\Settings as SellerSettings;
use App\Livewire\Storefront\Account\Addresses;
use App\Livewire\Storefront\Account\ReviewOrder;
use App\Livewire\Storefront\Auth\Register;
use App\Livewire\Storefront\Help\Tickets;
use App\Livewire\Storefront\Listing;
use App\Livewire\Storefront\ProductQuestions;
use App\Livewire\Storefront\ProductReviews;
use App\Livewire\Storefront\StorePage;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnReason;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\CartService;
use App\Support\HtmlSanitizer;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(fn () => test()->seed(RoleSeeder::class));

// ── fixtures ────────────────────────────────────────────────────────────

/**
 * A REAL UploadedFile whose mime type is guessed from its CONTENT.
 *
 * UploadedFile::fake()->createWithContent() cannot be used for mime tests:
 * Illuminate\Http\Testing\File::getMimeType() returns MimeType::from($name),
 * i.e. it derives the type from the FILENAME. A fake "evil.jpg" therefore
 * reports image/jpeg no matter what bytes are inside, so a mimes/image rule
 * "passes" it in the test and would reject it in production — the test double
 * lies in the direction that manufactures a false finding.
 */
function qaRealUpload(string $name, string $content): TestingFile
{
    $file = UploadedFile::fake()->createWithContent($name, $content);

    // Force the reported type to come from finfo on the real bytes, which is
    // what Symfony/Flysystem do outside the test harness.
    $detected = @mime_content_type($file->getRealPath()) ?: 'application/octet-stream';

    return $file->mimeType($detected);
}

function qaSeller(): User
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user->fresh();
}

function qaBuyerWithReviewableItem(): array
{
    $buyer = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Completed)->create(['order_id' => $order->id]);
    $product = Product::factory()->create(['store_id' => $subOrder->store_id, 'status' => ProductStatus::Live]);

    $item = $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants()->first()->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'unit_price_sen' => 2500,
        'qty' => 1,
        'line_total_sen' => 2500,
    ]);

    return [$buyer, $subOrder, $item, $product];
}

/** Markup that only ever fires if it reaches the browser as LIVE html. */
const QA_LIVE_MARKUP = '<img src=x onerror=alert(1)>';

// ══════════════════════════════════════════════════════════════════════
// 1. Stored XSS
// ══════════════════════════════════════════════════════════════════════

/**
 * libxml parses the body of raw-text elements (<script>/<style>/<noembed>/
 * <noframes>/<plaintext>) into a DOMCdataSection (nodeType 4).
 * HtmlSanitizer::sanitizeChildren() deletes only DOMComment and
 * DOMProcessingInstruction, so the CDATA node survives, is re-parented to the
 * wrapper <div> when the raw-text tag is unwrapped, and saveHTML() serialises
 * CDATA WITHOUT escaping — the payload comes back out as live markup.
 */
it('sanitizer does not re-emit raw-text element contents as live markup', function (string $payload) {
    $clean = HtmlSanitizer::clean($payload);

    expect($clean)->not->toContain('<img')
        ->and($clean)->not->toContain('onerror');
})->with([
    'script' => '<script>'.QA_LIVE_MARKUP.'</script>',
    'style' => '<style>'.QA_LIVE_MARKUP.'</style>',
    'svg>style' => '<svg><style>'.QA_LIVE_MARKUP.'</style></svg>',
    'noembed' => '<noembed>'.QA_LIVE_MARKUP.'</noembed>',
    'noframes' => '<noframes>'.QA_LIVE_MARKUP.'</noframes>',
    'plaintext' => '<plaintext>'.QA_LIVE_MARKUP,
]);

/** End-to-end: seller writes it, an anonymous visitor's PDP renders it live. */
it('does not render a seller-supplied img/onerror on the public product page', function () {
    $seller = qaSeller();
    $category = Category::factory()->create();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'QA Sanitizer Bypass Item')
        ->set('categoryTop', $category->id)
        ->set('price', '9.90')
        ->set('stock', '5')
        ->set('description.en', '<p>Nice product</p><script>'.QA_LIVE_MARKUP.'</script>')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $product = Product::query()->where('store_id', $seller->store->id)->firstOrFail();
    // Publishing is a separate gate; the defect is the stored body, so put the
    // product live directly rather than re-testing publish here.
    $product->update(['status' => ProductStatus::Live, 'published_at' => now()]);
    $product->variants()->update(['stock' => 5]);

    $html = $this->get('/p/'.$product->slug)->assertOk()->getContent();

    expect($html)->not->toContain('onerror=alert(1)');
});

/**
 * CMS body uses strip_tags() with a TAG allow-list, which keeps every
 * ATTRIBUTE on the surviving tags (and <a> is on the list).
 */
it('does not keep event handlers or javascript: urls on an admin-authored CMS page', function () {
    $admin = makeAdmin(User::factory()->create());

    Livewire::actingAs($admin)
        ->test(Pages::class)
        ->call('create')
        ->set('slug', 'qa-injection-page')
        ->set('title.en', 'QA Page')
        ->set('body.en', '<p onclick="alert(1)">hello</p><a href="javascript:alert(2)">link</a>')
        ->set('isActive', true)
        ->call('save')
        ->assertHasNoErrors();

    $html = $this->get('/page/qa-injection-page')->assertOk()->getContent();

    expect($html)->not->toContain('onclick="alert(1)"')
        ->and($html)->not->toContain('javascript:alert(2)');
});

it('escapes a product name on the public product page', function () {
    $payload = '"><img src=x onerror=alert(1)>';

    $product = Product::factory()->create([
        'name' => ['en' => $payload, 'ms' => $payload],
        'status' => ProductStatus::Live,
    ]);
    $product->variants()->update(['stock' => 5]);

    $html = $this->get('/p/'.$product->slug)->assertOk()->getContent();

    expect($html)->not->toContain(QA_LIVE_MARKUP);
});

it('escapes a store name and bio on the storefront store page', function () {
    $payload = '"><img src=x onerror=alert(1)>';
    $store = Store::factory()->approved()->create([
        'name' => $payload,
        'description' => 'Bio '.QA_LIVE_MARKUP,
    ]);

    $html = Livewire::test(StorePage::class, ['store' => $store])->html();

    expect($html)->not->toContain(QA_LIVE_MARKUP);
});

it('escapes a buyer review comment when another visitor reads the product page', function () {
    $payload = 'Great stuff '.QA_LIVE_MARKUP.' really';
    [$buyer, $subOrder, $item, $product] = qaBuyerWithReviewableItem();

    Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$item->id}", 5)
        ->set("comments.{$item->id}", $payload)
        ->call('submit', $item->id)
        ->assertHasNoErrors();

    $html = Livewire::withoutLazyLoading()->test(ProductReviews::class, ['product' => $product])->html();

    expect($html)->toContain('Great stuff')
        ->and($html)->not->toContain(QA_LIVE_MARKUP);
});

it('escapes a buyer product question and the seller answer', function () {
    $payload = 'Is this halal '.QA_LIVE_MARKUP.' certified?';
    $buyer = User::factory()->create();
    $product = Product::factory()->create(['status' => ProductStatus::Live]);

    Livewire::actingAs($buyer)
        ->test(ProductQuestions::class, ['product' => $product])
        ->set('question', $payload)
        ->call('ask')
        ->assertHasNoErrors();

    expect($product->questions()->count())->toBe(1);

    $product->questions()->update(['answer' => 'Yes '.QA_LIVE_MARKUP, 'answered_at' => now()]);

    $html = Livewire::withoutLazyLoading()->test(ProductQuestions::class, ['product' => $product])->html();

    expect($html)->toContain('Is this halal')
        ->and($html)->not->toContain(QA_LIVE_MARKUP);
});

it('escapes buyer address fields on the buyer address book', function () {
    $buyer = User::factory()->create();

    Livewire::actingAs($buyer)
        ->test(Addresses::class)
        ->call('create')
        ->set('recipient_name', QA_LIVE_MARKUP)
        ->set('phone', '0123456789')
        ->set('line1', QA_LIVE_MARKUP)
        ->set('city', QA_LIVE_MARKUP)
        ->set('postcode', '50000')
        ->set('state', 'Selangor')
        ->call('save')
        ->assertHasNoErrors();

    $html = Livewire::actingAs($buyer)->test(Addresses::class)->html();

    expect($html)->not->toContain(QA_LIVE_MARKUP);
});

it('escapes a buyer return-request description on the seller order screen', function () {
    $seller = qaSeller();
    $buyer = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::ReturnRequested)->create([
        'order_id' => $order->id,
        'store_id' => $seller->store->id,
    ]);

    $reason = ReturnReason::query()->create([
        'code' => 'qa-'.uniqid(),
        'label' => ['en' => 'Damaged', 'ms' => 'Rosak'],
        'is_active' => true,
    ]);

    ReturnRequest::create([
        'sub_order_id' => $subOrder->id,
        'return_reason_id' => $reason->id,
        'description' => 'Arrived broken '.QA_LIVE_MARKUP,
        'status' => ReturnStatus::Requested,
        'respond_by' => now()->addDay(),
    ]);

    $html = Livewire::actingAs($seller)
        ->test(Detail::class, ['subOrder' => $subOrder])
        ->html();

    expect($html)->toContain('Arrived broken')
        ->and($html)->not->toContain(QA_LIVE_MARKUP);
});

it('escapes a support ticket body on the admin ticket screen', function () {
    $payload = 'My order broke '.QA_LIVE_MARKUP.' please help me right now';
    $buyer = User::factory()->create();

    Livewire::actingAs($buyer)
        ->test(Tickets::class)
        ->call('startTicket')
        ->set('subject', 'QA injection subject')
        ->set('body', $payload)
        ->call('create')
        ->assertHasNoErrors();

    $admin = makeAdmin(User::factory()->create());

    $html = Livewire::actingAs($admin)->test(AdminTickets::class)->html();

    expect($html)->toContain('QA injection subject')
        ->and($html)->not->toContain(QA_LIVE_MARKUP);
});

// ══════════════════════════════════════════════════════════════════════
// 2. SQL-injection-shaped input + sort whitelisting
// ══════════════════════════════════════════════════════════════════════

it('binds the search term rather than interpolating it', function () {
    Product::factory()->count(3)->create(['status' => ProductStatus::Live]);

    $before = Product::query()->live()->count();
    expect($before)->toBeGreaterThan(0);

    foreach (["' OR '1'='1", "'; DROP TABLE products; --", '" UNION SELECT 1,2,3 --', "\\' OR 1=1 -- "] as $term) {
        expect(Product::searchKeywordIds($term))->toBe([]);
    }

    expect(Product::query()->live()->count())->toBe($before);
});

it('ignores a sort column the client invents', function () {
    Product::factory()->count(2)->create(['status' => ProductStatus::Live]);

    Livewire::test(Listing::class)
        ->set('q', 'a')
        ->set('sort', 'products.id) --')
        ->assertOk();

    Livewire::test(Listing::class)
        ->set('q', 'a')
        ->set('sort', '(select 1)')
        ->assertOk();
});

it('does not let a bare LIKE wildcard in the search term match every product', function () {
    Product::factory()->create([
        'name' => ['en' => 'Alpha Widget', 'ms' => 'Alpha Widget'],
        'status' => ProductStatus::Live,
    ]);
    Product::factory()->create([
        'name' => ['en' => 'Beta Gadget', 'ms' => 'Beta Gadget'],
        'status' => ProductStatus::Live,
    ]);

    // "%" is a LIKE metacharacter; unescaped it becomes '%%%' and matches all.
    expect(Product::searchKeywordIds('%'))->toBeEmpty()
        ->and(Product::searchKeywordIds('_'))->toBeEmpty();
});

// ══════════════════════════════════════════════════════════════════════
// 3. Search correctness on MariaDB (JSON columns are BINARY-collated)
// ══════════════════════════════════════════════════════════════════════

it('is running on mariadb so the collation tests mean something', function () {
    // The DRIVER is the guard that matters — the collation tests below are
    // vacuous on SQLite. Pinning a specific database NAME only broke the test
    // when it ran against a different one.
    expect(DB::connection()->getDriverName())->toBe('mysql');
});

it('finds a product by a differently cased substring of its JSON name on mysql', function (string $term) {
    $product = Product::factory()->create([
        'name' => ['en' => 'Beras Wangi Super Tempatan', 'ms' => 'Beras Wangi Super Tempatan'],
        'status' => ProductStatus::Live,
        'published_at' => now(),
    ]);

    expect(Product::searchKeywordIds($term))->toContain($product->id);
})->with(['Beras', 'beras', 'BERAS', 'bErAs', 'wangi', 'WANGI', 'tempatan']);

it('finds a product by a differently cased substring of its JSON description on mysql', function (string $term) {
    $product = Product::factory()->create([
        'name' => ['en' => 'Unrelated Product Name', 'ms' => 'Unrelated Product Name'],
        'description' => ['en' => '<p>Certified by JAKIM under MS 1500:2019</p>', 'ms' => '<p>Disahkan JAKIM</p>'],
        'status' => ProductStatus::Live,
        'published_at' => now(),
    ]);

    expect(Product::searchKeywordIds($term))->toContain($product->id);
})->with(['JAKIM', 'jakim', 'Jakim', 'certified', 'CERTIFIED']);

it('finds a lowercase-searched product through the real /search page on mysql', function () {
    $product = Product::factory()->create([
        'name' => ['en' => 'Beras Wangi Super Tempatan', 'ms' => 'Beras Wangi Super Tempatan'],
        'status' => ProductStatus::Live,
        'published_at' => now(),
    ]);
    $product->variants()->update(['stock' => 10]);

    $html = $this->get('/search?q=beras')->assertOk()->getContent();

    expect($html)->toContain('Beras Wangi Super Tempatan');
});

it('finds a non-ascii product name regardless of case on mysql', function () {
    $product = Product::factory()->create([
        'name' => ['en' => 'Kürma Ajwa Premium', 'ms' => 'Kürma Ajwa Premium'],
        'status' => ProductStatus::Live,
        'published_at' => now(),
    ]);

    expect(Product::searchKeywordIds('kürma'))->toContain($product->id);
});

// ══════════════════════════════════════════════════════════════════════
// 4. Uploads
// ══════════════════════════════════════════════════════════════════════

/**
 * The product form's own rule ('image', 'max:4096') against a file whose
 * reported type comes from its CONTENT, which is what production sees.
 */
it('rejects a wrong-content file renamed .jpg against the product-image rule', function (string $content) {
    $validator = Validator::make(
        ['newImages' => [qaRealUpload('evil.jpg', $content)]],
        ['newImages.*' => ['image', 'max:4096']]
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toContain('newImages.0');
})->with([
    'php' => "<?php system(\$_GET['c']); ?>\n",
    'html' => '<html><body><script>alert(1)</script></body></html>',
    'svg' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><text>x</text></svg>',
    'zero-byte' => '',
]);

/**
 * The rejection above never reaches the seller: form.blade.php line 226 calls
 * $image->temporaryUrl() on every pending upload with no isPreviewable()
 * guard (the sibling video preview on line 255 HAS one). Livewire throws for
 * any file outside livewire.temporary_file_upload.preview_mimes, so the
 * re-render that should display "must be an image" 500s instead.
 */
it('does not crash the product form when a seller attaches a non-image renamed .jpg', function () {
    Storage::fake('public');
    $seller = qaSeller();
    $category = Category::factory()->create();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'QA Upload Preview Crash')
        ->set('categoryTop', $category->id)
        ->set('price', '9.90')
        ->set('newImages', [qaRealUpload('evil.jpg', '<html><body>hi</body></html>')])
        ->call('saveDraft')
        ->assertHasErrors(['newImages.0']);
});

it('rejects an svg renamed .jpg as a store logo', function () {
    Storage::fake('public');
    $seller = qaSeller();

    Livewire::actingAs($seller)
        ->test(SellerSettings::class)
        ->set('description', 'An ordinary shop description for the QA pass.')
        ->set('state', 'Selangor')
        ->set('logo', qaRealUpload('logo.jpg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><text>x</text></svg>'))
        ->call('saveProfile')
        ->assertHasErrors(['logo']);
});

it('rejects an oversized product image', function () {
    Storage::fake('public');
    $seller = qaSeller();
    $category = Category::factory()->create();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'QA Oversize Item')
        ->set('categoryTop', $category->id)
        ->set('price', '9.90')
        ->set('newImages', [UploadedFile::fake()->image('big.jpg', 100, 100)->size(8192)])
        ->call('saveDraft')
        ->assertHasErrors(['newImages.0']);
});

it('rejects an html payload renamed .jpg as a review photo', function () {
    Storage::fake('public');
    [$buyer, $subOrder, $item] = qaBuyerWithReviewableItem();

    Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$item->id}", 5)
        ->set("photos.{$item->id}", [qaRealUpload('evil.jpg', '<html><script>alert(1)</script></html>')])
        ->call('submit', $item->id)
        ->assertHasErrors();
});

/** Accepted product images are public by design — record WHERE they land. */
it('stores an accepted product image on the public disk under an unguessable name', function () {
    Storage::fake('public');
    $seller = qaSeller();
    $category = Category::factory()->create();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'QA Accepted Image Item')
        ->set('categoryTop', $category->id)
        ->set('price', '9.90')
        ->set('newImages', [UploadedFile::fake()->image('photo.jpg', 200, 200)])
        ->call('saveDraft')
        ->assertHasNoErrors();

    $media = Product::query()->where('name->en', 'QA Accepted Image Item')->firstOrFail()
        ->getMedia('images')->first();

    expect($media)->not->toBeNull()
        ->and($media->disk)->toBe('public')
        ->and($media->file_name)->toEndWith('.jpg');
});

it('requires a signature to read a file off the private local disk', function () {
    Storage::disk('local')->put('kyc/secret.pdf', 'CONFIDENTIAL');

    $response = $this->get('/storage/kyc/secret.pdf');

    expect($response->status())->toBeIn([403, 404]);
    expect($response->getContent())->not->toContain('CONFIDENTIAL');
});

// ══════════════════════════════════════════════════════════════════════
// 5. Mass assignment
// ══════════════════════════════════════════════════════════════════════

it('does not let a seller set their own commission rate through the settings form', function () {
    $seller = qaSeller();
    $before = $seller->store->commission_rate;

    Livewire::actingAs($seller)
        ->test(SellerSettings::class)
        ->set('description', 'A perfectly ordinary shop description.')
        ->set('state', 'Selangor')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($seller->store->fresh()->commission_rate)->toEqual($before);
});

it('writes a new product to the acting seller store, never a store id the client names', function () {
    $sellerA = qaSeller();
    $sellerB = qaSeller();
    $category = Category::factory()->create();

    Livewire::actingAs($sellerA)
        ->test(Form::class)
        ->set('name.en', 'QA Ownership Item')
        ->set('categoryTop', $category->id)
        ->set('price', '9.90')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $product = Product::query()->where('name->en', 'QA Ownership Item')->firstOrFail();

    expect($product->store_id)->toBe($sellerA->store->id)
        ->and($product->store_id)->not->toBe($sellerB->store->id);
});

it('stamps a review with the sub-order store, not anything the buyer supplies', function () {
    [$buyer, $subOrder, $item] = qaBuyerWithReviewableItem();

    Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$item->id}", 5)
        ->set("comments.{$item->id}", 'A perfectly ordinary review body here.')
        ->call('submit', $item->id)
        ->assertHasNoErrors();

    $review = DB::table('reviews')->where('order_item_id', $item->id)->first();

    expect($review)->not->toBeNull()
        ->and((int) $review->store_id)->toBe($subOrder->store_id)
        ->and((int) $review->user_id)->toBe($buyer->id);
});

it('gives a self-registered user the buyer role only, whatever context they claim', function () {
    Livewire::test(Register::class, ['context' => 'admin'])
        ->set('context', 'admin')
        ->set('name', 'QA Escalator')
        ->set('email', 'qa-escalator@example.com')
        ->set('password', 'Password123!x')
        ->set('password_confirmation', 'Password123!x')
        ->set('terms', true)
        ->call('register');

    $user = User::query()->where('email', 'qa-escalator@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe(['buyer']);
});

it('rejects a public property the component never declared', function () {
    expect(fn () => Livewire::test(Register::class)->set('is_superadmin', 1))
        ->toThrow(Exception::class);
});

// ══════════════════════════════════════════════════════════════════════
// 6. Validation completeness — numeric / boundary
// ══════════════════════════════════════════════════════════════════════

/**
 * RinggitInput::toSen() strips every non-[0-9.] character, so "-50.00"
 * becomes "50.00" → 5000 sen. Nothing negative can ever reach the DB (good),
 * but the seller's input is silently reinterpreted instead of rejected.
 */
it('rejects a negative product price instead of silently flipping its sign', function () {
    $seller = qaSeller();
    $category = Category::factory()->create();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'QA Negative Price Item')
        ->set('categoryTop', $category->id)
        ->set('price', '-50.00')
        ->call('saveDraft')
        ->assertHasErrors(['price']);
});

it('never lets a negative price reach the database', function () {
    $seller = qaSeller();
    $category = Category::factory()->create();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'QA Sign Flip Item')
        ->set('categoryTop', $category->id)
        ->set('price', '-50.00')
        ->call('saveDraft');

    $product = Product::query()->where('name->en', 'QA Sign Flip Item')->first();

    if ($product !== null) {
        expect((int) $product->variants()->first()->price_sen)->toBeGreaterThan(0);
    }

    expect(DB::table('product_variants')->where('price_sen', '<', 0)->count())->toBe(0);
});

it('rejects negative stock', function () {
    $seller = qaSeller();
    $category = Category::factory()->create();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'QA Negative Stock Item')
        ->set('categoryTop', $category->id)
        ->set('price', '10.00')
        ->set('stock', '-5')
        ->call('saveDraft')
        ->assertHasErrors(['stock']);
});

it('rejects negative physical dimensions on a product', function () {
    $seller = qaSeller();
    $category = Category::factory()->create();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'QA Negative Weight Item')
        ->set('categoryTop', $category->id)
        ->set('price', '10.00')
        ->set('weightGrams', -1000)
        ->call('saveDraft')
        ->assertHasErrors(['weightGrams']);
});

it('never writes a non-positive cart quantity', function () {
    $buyer = User::factory()->create();
    $product = Product::factory()->create(['status' => ProductStatus::Live]);
    $variant = $product->variants()->first();
    $variant->update(['stock' => 10]);

    app(CartService::class)->addItem($buyer, $variant, -5);

    $row = DB::table('cart_items')
        ->join('carts', 'carts.id', '=', 'cart_items.cart_id')
        ->where('carts.user_id', $buyer->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and((int) $row->qty)->toBeGreaterThan(0);
});

it('rejects a postcode that is not five digits', function (string $postcode) {
    $buyer = User::factory()->create();

    Livewire::actingAs($buyer)
        ->test(Addresses::class)
        ->call('create')
        ->set('recipient_name', 'Ali')
        ->set('phone', '0123456789')
        ->set('line1', '1 Jalan Satu')
        ->set('postcode', $postcode)
        ->set('city', 'Kuala Lumpur')
        ->set('state', 'Selangor')
        ->call('save')
        ->assertHasErrors(['postcode']);
})->with(['-1234', '1234', '123456', 'abcde', '12 34']);

it('rejects a state outside the Malaysian list', function () {
    $buyer = User::factory()->create();

    Livewire::actingAs($buyer)
        ->test(Addresses::class)
        ->call('create')
        ->set('recipient_name', 'Ali')
        ->set('phone', '0123456789')
        ->set('line1', '1 Jalan Satu')
        ->set('postcode', '50000')
        ->set('city', 'Kuala Lumpur')
        ->set('state', 'Atlantis')
        ->call('save')
        ->assertHasErrors(['state']);
});

it('rejects a review rating outside 1..5', function (int $rating) {
    [$buyer, $subOrder, $item] = qaBuyerWithReviewableItem();

    Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$item->id}", $rating)
        ->call('submit', $item->id)
        ->assertHasErrors();
})->with([0, -1, 6, 999]);

it('rejects an oversized product name past the 255 limit', function () {
    $seller = qaSeller();
    $category = Category::factory()->create();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', str_repeat('a', 300))
        ->set('categoryTop', $category->id)
        ->set('price', '9.90')
        ->call('saveDraft')
        ->assertHasErrors(['name.en']);
});
