<?php

use App\Models\Category;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\DisplayTime;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

try {
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $root = getenv('HB_E2E_APP_ROOT') ?: getcwd();
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $main = config('database.connections.'.config('database.default'));
    $fake = config('database.connections.whip');
    if (! in_array($main['host'] ?? '', ['127.0.0.1', 'localhost'], true)
        || ($main['database'] ?? '') !== 'hbiz2_qa'
        || ($fake['driver'] ?? '') !== 'sqlite'
        || ($fake['database'] ?? '') !== '/tmp/hb-e2e-whip.sqlite'
        || ! is_file($fake['database'])) {
        throw new RuntimeException('Local database or WHIP fake safety check failed.');
    }
    if (($input['action'] ?? '') !== 'init' && ! preg_match('/^e2e-[0-9]+-[a-f0-9]+$/', $input['id'] ?? '')) {
        throw new InvalidArgumentException('Invalid fixture identifier.');
    }
    $id = $input['id'] ?? '';
    $email = "$id@halalbizs.test";
    $pdo = new PDO('sqlite:'.$fake['database']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (in_array($input['action'], ['init', 'setup'], true)) {
        foreach ([
            'CREATE TABLE IF NOT EXISTS halal_applications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER DEFAULT 1, application_no TEXT, submitted_at TEXT, certification_body_id INTEGER, status TEXT)',
            'CREATE TABLE IF NOT EXISTS halal_application_certificates (id INTEGER PRIMARY KEY AUTOINCREMENT, application_id INTEGER, certificate_no TEXT, valid_from TEXT, valid_to TEXT, services_text TEXT)',
            'CREATE TABLE IF NOT EXISTS halal_application_company_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER DEFAULT 1, application_id INTEGER, company_name TEXT, registration_number TEXT)',
            'CREATE TABLE IF NOT EXISTS halal_application_products (id INTEGER PRIMARY KEY AUTOINCREMENT, application_id INTEGER, product_name TEXT, brand_name TEXT, barcode TEXT)',
            'CREATE TABLE IF NOT EXISTS certification_bodies (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)',
        ] as $sql) {
            $pdo->exec($sql);
        }
    }
    if ($input['action'] === 'init') {
        exit;
    }
    if ($input['action'] === 'setup') {
        $pdo->prepare('INSERT INTO certification_bodies (name) VALUES (?)')->execute(['E2E Test CB']);
        $body = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO halal_applications (application_no, certification_body_id, status) VALUES (?, ?, ?)')->execute([$id, $body, 'submitted']);
        $appId = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO halal_application_company_profiles (application_id, company_name, registration_number) VALUES (?, ?, ?)')->execute([$appId, 'E2E Foods', $id]);
        $pdo->prepare('INSERT INTO halal_application_certificates (application_id, certificate_no, valid_from, valid_to, services_text) VALUES (?, ?, ?, ?, ?)')->execute([$appId, strtoupper($id), now()->subMonth()->toDateString(), DisplayTime::today()->addDays(60)->toDateString(), 'E2E sauces']);
        $pdo->prepare('INSERT INTO halal_application_products (application_id, product_name, barcode) VALUES (?, ?, ?)')->execute([$appId, "E2E Sauce $id", $id]);
        $user = User::factory()->create(['name' => $id, 'email' => $email, 'password' => Hash::make($input['password'])]);
        $user->assignRole('seller');
        Store::factory()->approved()->create(['user_id' => $user->id, 'name' => $id, 'registration_no' => $id, 'registration_verified_at' => now()]);
        $category = Category::factory()->create(['name' => ['en' => "E2E category $id"]]);
        echo json_encode(['category' => $category->id]);
    } elseif ($input['action'] === 'certify') {
        $pdo->prepare('UPDATE halal_applications SET status = ? WHERE application_no = ?')->execute(['certified', $id]);
    } elseif ($input['action'] === 'reminder') {
        $user = User::where('email', $email)->firstOrFail();
        $certificate = $user->store->halalCertificates()->where('number', strtoupper($id))->firstOrFail();
        // Scope the real command to this fixture so other local sellers are untouched.
        HalalCertificate::addGlobalScope('e2e-owned', fn ($query) => $query->where('halal_certificates.id', $certificate->id));
        config(['queue.default' => 'sync', 'mail.default' => 'array']);
        if (Artisan::call('certificates:remind-whip-expiry') !== 0 || $user->notifications()->count() !== 1) {
            throw new RuntimeException('Owned reminder was not generated.');
        }
    } elseif ($input['action'] === 'buyer') {
        $user = User::where('email', $email)->firstOrFail();
        $product = Product::where('store_id', $user->store->id)->where('whip_product_id', '!=', null)->firstOrFail();
        $product->forceFill(['status' => 'live', 'published_at' => now()])->save();
        echo json_encode(['slug' => $product->slug]);
    } elseif ($input['action'] === 'unverify') {
        User::where('email', $email)->firstOrFail()->store->forceFill(['registration_verified_at' => null])->save();
    } elseif ($input['action'] === 'cleanup') {
        $user = User::where('email', $email)->first();
        if ($user) {
            $user->notifications()->delete();
            $store = $user->store;
            $productIds = $store ? Product::withTrashed()->where('store_id', $store->id)->pluck('id') : collect();
            $storeId = $store?->id;
            if ($store) {
                Product::withTrashed()->where('store_id', $store->id)->forceDelete();
                $store->halalCertificates()->delete();
                $store->forceDelete();
            }
            DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', $user->id)->delete();
            $userId = $user->id;
            $user->forceDelete();
            DB::table('activity_log')->where(function ($query) use ($userId, $storeId, $productIds) {
                $query->where(fn ($q) => $q->where('causer_type', User::class)->where('causer_id', $userId))
                    ->orWhere(fn ($q) => $q->where('subject_type', Store::class)->where('subject_id', $storeId))
                    ->orWhere(fn ($q) => $q->where('subject_type', Product::class)->whereIn('subject_id', $productIds));
            })->delete();
        }
        Category::where('name->en', "E2E category $id")->delete();
        $q = $pdo->prepare('SELECT id, certification_body_id FROM halal_applications WHERE application_no = ?');
        $q->execute([$id]);
        if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            foreach (['halal_application_products', 'halal_application_certificates', 'halal_application_company_profiles'] as $table) {
                $pdo->prepare("DELETE FROM $table WHERE application_id = ?")->execute([$row['id']]);
            }
            $pdo->prepare('DELETE FROM halal_applications WHERE id = ?')->execute([$row['id']]);
            $pdo->prepare('DELETE FROM certification_bodies WHERE id = ?')->execute([$row['certification_body_id']]);
        }
    } else {
        throw new InvalidArgumentException('Unknown fixture action.');
    }

} catch (Throwable $error) {
    fwrite(STDERR, "WHIP fixture failed.\n");
    exit(1);
}
