<?php

use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

it('shows a plus before each create action', function (string $role, string $component, string $label, int $count) {
    (new RoleSeeder)->run();
    $user = User::factory()->create(['two_factor_method' => 'email']);
    if ($role === 'admin') {
        $user = makeAdmin($user);
    } elseif ($role === 'seller') {
        $user->assignRole('seller');
        Store::factory()->approved()->create(['user_id' => $user->id]);
    }

    $html = Livewire::actingAs($user)->test($component)->html();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($dom);
    $actions = $xpath->query('//button[normalize-space(.) = '."'".$label."'".'] | //a[normalize-space(.) = '."'".$label."'".']');
    expect($actions->length)->toBe($count);
    foreach ($actions as $action) {
        $icons = $xpath->query('.//*[local-name()="svg"]/*[local-name()="path" and @d="M12 4.5v15m7.5-7.5h-15"]', $action);
        expect($icons->length)->toBe(1)
            ->and($xpath->query('./*[1][local-name()="svg"]', $action)->length)->toBe(1);
    }
})->with([
    ['seller', App\Livewire\Seller\GroupBuys::class, 'Create deal', 1],
    ['seller', App\Livewire\Seller\LiveSessions::class, 'New session', 1],
    ['seller', App\Livewire\Seller\Certificates::class, 'Register a certificate', 1],
    ['seller', App\Livewire\Seller\Products\Index::class, 'Add your first product', 1],
    ['seller', App\Livewire\Seller\Products\Index::class, 'Add product', 1],
    ['seller', App\Livewire\Seller\Vouchers\Index::class, 'Add voucher', 1],
    ['admin', App\Livewire\Admin\Catalog\Categories::class, 'Add root category', 2],
    ['admin', App\Livewire\Admin\Content\HomeSections::class, 'Add section', 1],
    ['admin', App\Livewire\Admin\Content\HomeSections::class, 'New scheduled layout', 1],
    ['admin', App\Livewire\Admin\Catalog\Attributes::class, 'Add attribute', 1],
    ['admin', App\Livewire\Admin\Catalog\Brands::class, 'Add brand', 1],
    ['admin', App\Livewire\Admin\Content\FlashSales::class, 'Create flash sale', 1],
    ['admin', App\Livewire\Admin\Content\Banners::class, 'Add banner', 1],
    ['admin', App\Livewire\Admin\Content\Pages::class, 'Add page', 1],
    ['admin', App\Livewire\Admin\Content\Vouchers::class, 'Add voucher', 1],
    ['admin', App\Livewire\Admin\Support\Articles::class, 'Add article', 1],
    ['admin', App\Livewire\Admin\System\Staff::class, 'Invite admin', 1],
    ['buyer', App\Livewire\Storefront\Account\Addresses::class, 'Add address', 1],
    ['buyer', App\Livewire\Storefront\Help\Tickets::class, 'New ticket', 1],
]);
