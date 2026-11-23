<?php

use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnReason;
use App\Models\Review;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\ProductWriteService;
use App\Services\ReturnService;
use App\Services\ReviewService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Support\PhotoMetadata;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
 * HB-B2: a phone photo's EXIF can carry the GPS position it was taken at
 * (often a home). Every stored original must come out without it, upright.
 * The fixture is built here: a 40×20 JPEG with a red top-left corner, EXIF
 * Orientation 6 (show rotated 90° clockwise) and FAKE GPS 1°2'3" N 4°5'6" E.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
    Storage::fake('local');
});

/** Path to a temp JPEG carrying GPS + orientation EXIF. */
function gpsJpeg(int $orientation = 6, int $width = 40, int $height = 20): string
{
    $img = imagecreatetruecolor($width, $height);
    imagefill($img, 0, 0, imagecolorallocate($img, 0, 0, 255));
    imagefilledrectangle($img, 0, 0, 9, 9, imagecolorallocate($img, 255, 0, 0));
    ob_start();
    imagejpeg($img, null, 95);
    $jpeg = ob_get_clean();

    // TIFF, little endian. IFD0 at 8 (2 entries) → GPS IFD at 38 (4 entries) → rationals at 92.
    $entry = fn (int $tag, int $type, int $count, string $value) => pack('vvV', $tag, $type, $count).str_pad($value, 4, "\0");
    $tiff = "II*\0".pack('V', 8)
        .pack('v', 2).$entry(0x0112, 3, 1, pack('v', $orientation)).$entry(0x8825, 4, 1, pack('V', 38)).pack('V', 0)
        .pack('v', 4)
        .$entry(0x0001, 2, 2, "N\0").$entry(0x0002, 5, 3, pack('V', 92))
        .$entry(0x0003, 2, 2, "E\0").$entry(0x0004, 5, 3, pack('V', 116)).pack('V', 0)
        .pack('V*', 1, 1, 2, 1, 3, 1).pack('V*', 4, 1, 5, 1, 6, 1);
    $app1 = "Exif\0\0".$tiff;
    $bytes = "\xFF\xD8\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);

    $path = tempnam(sys_get_temp_dir(), 'gps').'.jpg';
    file_put_contents($path, $bytes);

    return $path;
}

function gpsUpload(): UploadedFile
{
    return new UploadedFile(gpsJpeg(), 'IMG_0001.jpg', 'image/jpeg', null, true);
}

/** Independent check: PHP's own EXIF reader. */
function exifGps(string $path): bool
{
    $exif = @exif_read_data($path);

    return is_array($exif) && isset($exif['GPSLatitude']);
}

/** Stored file must be upright (20×40, red corner now top-right) and GPS-free, size column matching. */
function expectStripped(Media $media): void
{
    $path = $media->getPath();
    [$w, $h] = getimagesize($path);
    $img = imagecreatefromjpeg($path);
    $topRight = imagecolorsforindex($img, imagecolorat($img, 15, 3));

    expect(exifGps($path))->toBeFalse()
        ->and(str_contains((string) file_get_contents($path), "Exif\0\0"))->toBeFalse()
        ->and([$w, $h])->toBe([20, 40])
        ->and($topRight['red'])->toBeGreaterThan(200)
        ->and($topRight['blue'])->toBeLessThan(60)
        ->and((int) $media->fresh()->size)->toBe(filesize($path))
        ->and($media->fresh()->file_name)->toBe($media->file_name);
}

test('the fixture really carries GPS (guards the red check)', function () {
    expect(exifGps(gpsJpeg()))->toBeTrue();
});

test('a buyer review photo is stored without GPS and upright', function () {
    $buyer = User::factory()->create();
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Completed)->create(['order_id' => Order::factory()->create(['user_id' => $buyer->id])->id]);
    $product = Product::factory()->create(['store_id' => $subOrder->store_id]);
    $item = $subOrder->items()->create([
        'product_id' => $product->id, 'product_variant_id' => $product->variants()->first()->id,
        'product_name' => 'Sambal', 'unit_price_sen' => 2500, 'qty' => 1, 'line_total_sen' => 2500,
    ]);

    app(ReviewService::class)->submit($subOrder, $buyer, [
        ['order_item_id' => $item->id, 'rating' => 5, 'photos' => [gpsUpload()]],
    ]);

    expectStripped(Review::where('order_item_id', $item->id)->firstOrFail()->getFirstMedia('photos'));
});

test('a buyer return photo is stored without GPS and upright', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Delivered)->create([
        'order_id' => Order::factory()->create(['user_id' => $buyer->id])->id,
        'store_id' => Store::factory()->approved()->create()->id,
    ]);
    $reason = ReturnReason::firstOrCreate(['position' => 0], ['label' => ['en' => 'Damaged'], 'is_active' => true]);

    $request = app(ReturnService::class)->request($subOrder, $buyer, $reason, null, [gpsUpload()]);

    expectStripped($request->getFirstMedia('photos'));
});

test('a seller product image is stored without GPS and upright', function () {
    $product = Product::factory()->create();

    app(ProductWriteService::class)->addImages($product, [gpsUpload()]);

    expectStripped($product->getFirstMedia('images'));
});

test('a PDF upload is left byte for byte', function () {
    $product = Product::factory()->create();
    $pdf = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($pdf, "%PDF-1.4\n% Exif\0\0 GPSLatitude\n%%EOF\n");
    $before = hash_file('sha256', $pdf);

    $media = $product->addMedia($pdf)->usingFileName('doc.pdf')->toMediaCollection('videos');

    expect(hash_file('sha256', $media->getPath()))->toBe($before);
});

test('media:strip-exif dry run touches nothing; --apply strips; a re-run finds nothing', function () {
    $product = Product::factory()->create();
    // Seed an "old" upload: write the GPS original straight over a stored file, as live files are today.
    $media = $product->addMedia(UploadedFile::fake()->image('a.jpg', 40, 20))->toMediaCollection('images');
    copy(gpsJpeg(), $media->getPath());
    Media::query()->whereKey($media->id)->toBase()->update(['size' => filesize($media->getPath())]);
    $path = $media->getPath();
    $hash = hash_file('sha256', $path);
    $mtime = filemtime($path);
    $updatedAt = $media->fresh()->updated_at;

    $this->artisan('media:strip-exif')
        ->expectsOutputToContain('DRY RUN')
        ->expectsTable(
            ['Collection', 'Files', 'Images', 'With EXIF', 'With GPS', 'Rewritten', 'Errors'],
            [['Product/images', 1, 1, 1, 1, 0, 0]],
        )
        ->assertSuccessful();

    clearstatcache();
    expect(hash_file('sha256', $path))->toBe($hash)->and(filemtime($path))->toBe($mtime);

    $this->artisan('media:strip-exif', ['--apply' => true])
        ->expectsTable(
            ['Collection', 'Files', 'Images', 'With EXIF', 'With GPS', 'Rewritten', 'Errors'],
            [['Product/images', 1, 1, 1, 1, 1, 0]],
        )
        ->assertSuccessful();

    expectStripped($media);
    expect($media->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();

    $this->artisan('media:strip-exif', ['--apply' => true])
        ->expectsTable(
            ['Collection', 'Files', 'Images', 'With EXIF', 'With GPS', 'Rewritten', 'Errors'],
            [['Product/images', 1, 1, 0, 0, 0, 0]],
        )
        ->assertSuccessful();
});

test('a JPEG too big to decode in memory still loses its GPS and keeps its orientation', function () {
    $path = gpsJpeg(6, 2000, 1500); // decoding + turning it needs ~30 MB; the limit below leaves 8 MB
    $limit = ini_get('memory_limit');
    ini_set('memory_limit', (string) (memory_get_usage(true) + 8 * 1024 * 1024));
    try {
        $stripped = PhotoMetadata::strip($path);
    } finally {
        ini_set('memory_limit', $limit);
    }

    $exif = exif_read_data($path);
    expect($stripped)->toBeTrue()
        ->and(isset($exif['GPSLatitude']))->toBeFalse()
        ->and($exif['Orientation'])->toBe(6) // not re-encoded: the tag keeps it upright on screen
        ->and(getimagesize($path)[0])->toBe(2000)
        ->and(imagecreatefromjpeg($path))->toBeInstanceOf(GdImage::class);
});

test('hostile metadata blocks end fast without a fatal', function (string $bytes) {
    $path = tempnam(sys_get_temp_dir(), 'bad');
    file_put_contents($path, $bytes);
    $start = microtime(true);

    try {
        PhotoMetadata::strip($path);
    } catch (RuntimeException) {
        // undecodable: the listener logs it
    }

    expect(microtime(true) - $start)->toBeLessThan(1.0);
})->with([
    'APP1 longer than the file' => ["\xFF\xD8\xFF\xE1\xFF\xFFExif\0\0II*\0\x08\0\0\0"],
    'segment length 0' => ["\xFF\xD8\xFF\xE1\x00\x00Exif\0\0".str_repeat("\xFF", 64)],
    // IFD0 says 65535 entries and its GPS pointer points back at IFD0 itself
    'IFD loop, 65535 entries' => ["\xFF\xD8\xFF\xE1\x00\x22Exif\0\0II*\0\x08\0\0\0\xFF\xFF\x25\x88\x04\0\x01\0\0\0\x08\0\0\0\0\0\0\0\xFF\xDA"],
    'PNG chunk length 0xFFFFFFFF' => ["\x89PNG\r\n\x1A\n\xFF\xFF\xFF\xFFeXIfII*\0"],
    'WebP chunk length 0xFFFFFFFF' => ["RIFF\0\0\0\0WEBPEXIF\xFF\xFF\xFF\xFFExif\0\0MM\0*"],
]);
