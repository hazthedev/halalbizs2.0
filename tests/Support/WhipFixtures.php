<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// WHIP is read through a second connection. Tests point it at in-memory SQLite
// and create only the columns the directory reads.

function createWhipTables(): void
{
    $s = Schema::connection('whip');
    $s->create('halal_applications', function ($t) {
        $t->id();
        $t->unsignedBigInteger('certification_body_id')->default(1);
        $t->string('status');
    });
    $s->create('halal_application_certificates', function ($t) {
        $t->id();
        $t->unsignedBigInteger('application_id');
        $t->string('certificate_no')->nullable();
        $t->date('valid_from')->nullable();
        $t->date('valid_to')->nullable();
    });
    $s->create('halal_application_company_profiles', function ($t) {
        $t->id();
        $t->unsignedBigInteger('application_id');
        $t->string('company_name')->nullable();
        $t->string('registration_number')->nullable();
    });
    $s->create('halal_application_products', function ($t) {
        $t->id();
        $t->unsignedBigInteger('application_id');
        $t->string('product_name');
        $t->string('brand_name')->nullable();
        $t->string('barcode')->nullable();
    });
    $s->create('certification_bodies', function ($t) {
        $t->id();
        $t->string('name');
    });
    DB::connection('whip')->table('certification_bodies')->insert(['id' => 1, 'name' => 'Test CB']);
}

/** One WHIP application with certificate + company; overrides per key. */
function whipCert(array $o = []): int
{
    $w = DB::connection('whip');
    $app = $w->table('halal_applications')->insertGetId(['status' => $o['status'] ?? 'certified']);
    $w->table('halal_application_certificates')->insert([
        'application_id' => $app,
        'certificate_no' => array_key_exists('no', $o) ? $o['no'] : 'WH-100',
        'valid_from' => $o['from'] ?? now()->subMonth()->toDateString(),
        'valid_to' => $o['to'] ?? now()->addYear()->toDateString(),
    ]);
    $w->table('halal_application_company_profiles')->insert([
        'application_id' => $app, 'company_name' => 'WHIP Foods Sdn Bhd', 'registration_number' => $o['reg'] ?? '202001-000123',
    ]);
    foreach ($o['products'] ?? [['product_name' => 'Kicap', 'barcode' => '955000']] as $p) {
        $w->table('halal_application_products')->insert($p + ['application_id' => $app]);
    }

    return $app;
}
