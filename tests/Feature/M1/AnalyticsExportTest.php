<?php

use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use App\Enums\StoreStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Seller\Earnings;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use App\Models\SubOrder;
use App\Models\User;
use App\Support\Csv;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('Csv::stream writes a header row and data rows', function () {
    $response = Csv::stream('x.csv', ['a', 'b'], [[1, 2], [3, 'he,llo']]);

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('a,b')
        ->toContain('1,2')
        ->toContain('"he,llo"'); // quoting of embedded comma
});

test('a seller can export their earnings ledger as CSV', function () {
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->create(['user_id' => $seller->id, 'status' => StoreStatus::Approved]);

    StoreLedgerEntry::create([
        'store_id' => $store->id, 'type' => LedgerEntryType::Sale, 'amount_sen' => 12000,
        'status' => LedgerEntryStatus::Available, 'description' => 'Sale SO1', 'created_at' => now(),
    ]);

    Livewire::actingAs($seller)
        ->test(Earnings::class)
        ->call('exportCsv')
        ->assertFileDownloaded("earnings-{$store->slug}.csv");
});

test('the admin take-rate is commission as a share of completed GMV', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    SubOrder::factory()->status(SubOrderStatus::Completed)->create([
        'total_sen' => 10000, 'commission_sen' => 500, 'completed_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertViewHas('takeRateBp', 500); // 5.00%
});
