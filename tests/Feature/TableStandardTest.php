<?php

use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Orders\Index as AdminOrders;
use App\Livewire\Admin\Subscriptions\Index as AdminSubscriptions;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

// HB-G165 (Haze 2026-11-19, ruling HB-DEC-1119m): one table standard on every admin + seller list.

function g165Admin(): User
{
    test()->seed(RoleSeeder::class);

    return makeAdmin(User::factory()->create(['two_factor_method' => 'email']));
}

test('the one pager is light only and highlights the current page', function () {
    $p = new LengthAwarePaginator(range(1, 20), 125, 20, 3, ['path' => '/x']);
    $html = view('pagination.table', ['paginator' => $p, 'elements' => [[1 => '/x?page=1', 2 => '/x?page=2', 3 => '/x?page=3'], '...', [7 => '/x?page=7']]])->render();

    // A `dark:` class follows the OS colour scheme: a dark-mode Mac painted the pager navy.
    expect($html)->not->toMatch('/dark:|gray-|blue-/')
        ->and($html)->toMatch('/<span aria-current="page"><span class="[^"]*bg-emerald[^"]*text-white/')
        ->and($html)->toContain('min-h-9');
});

test('a bare links() call renders the same pager as the table footer', function () {
    $p = new LengthAwarePaginator(range(1, 20), 125, 20, 3, ['path' => '/x']);
    $elements = [[1 => '/x?page=1', 2 => '/x?page=2', 3 => '/x?page=3']];
    $pager = view('pagination.table', ['paginator' => $p, 'elements' => $elements])->render();
    $bare = view('livewire::tailwind', ['paginator' => $p, 'elements' => $elements])->render();

    expect($bare)->toContain(trim(preg_replace('/^<div>|<\/div>$/', '', trim($pager))));
});

test('rows per page sits above the table, the footer keeps the summary and pager', function () {
    $admin = g165Admin();
    SubOrder::factory()->count(21)->create(['store_id' => Store::factory()->approved()->create()->id]);

    $html = Livewire::actingAs($admin)->test(AdminOrders::class)->html();
    $select = strpos($html, 'wire:model.live="perPage"');

    expect(substr_count($html, 'wire:model.live="perPage"'))->toBe(1)
        ->and($select)->toBeLessThan(strpos($html, '<table'))
        ->and(strpos($html, 'Showing 1 to 20 of 21'))->toBeGreaterThan(strpos($html, '</table>'))
        ->and(strpos($html, 'aria-label="Pagination Navigation"'))->toBeGreaterThan(strpos($html, '</table>'));

    $footer = Blade::render('<x-ui.table.footer :rows="$rows" />', ['rows' => new LengthAwarePaginator(range(1, 20), 125, 20, 1, ['path' => '/x'])]);
    expect($footer)->not->toContain('perPage')->and($footer)->toContain('Pagination Navigation');
});

test('only the active sort column shows an arrow, pointing the real direction', function () {
    $admin = g165Admin();
    SubOrder::factory()->count(2)->create(['store_id' => Store::factory()->approved()->create()->id]);
    $down = 'M12 5v14m-5-5 5 5 5-5';
    $up = 'M12 19V5m-5 5 5-5 5 5';

    // First load: the list is newest first, so Placed carries the one arrow, pointing down.
    $page = Livewire::actingAs($admin)->test(AdminOrders::class);
    $html = $page->html();
    expect(substr_count($html, 'aria-sort="descending"'))->toBe(1)
        ->and(substr_count($html, 'aria-sort="none"'))->toBe(2)
        ->and(substr_count($html, $down) + substr_count($html, $up))->toBe(1)
        ->and(strpos($html, $down))->toBeGreaterThan(strpos($html, 'Placed'))
        ->and(strpos($html, $down))->toBeLessThan(strpos($html, 'Total'));

    // Pick Total: Total ascending, Placed loses its arrow.
    $html = $page->call('sortBy', 'total')->html();
    expect(substr_count($html, 'aria-sort="ascending"'))->toBe(1)
        ->and(substr_count($html, 'aria-sort="none"'))->toBe(2)
        ->and(substr_count($html, $down) + substr_count($html, $up))->toBe(1)
        ->and(strpos($html, $up))->toBeGreaterThan(strpos($html, 'Total'));
});

test('header cells share one padding on the standard lists', function () {
    $admin = g165Admin();
    SubOrder::factory()->create(['store_id' => Store::factory()->approved()->create()->id]);
    $html = Livewire::actingAs($admin)->test(AdminOrders::class)->html();

    preg_match_all('/<th\b[^>]*class="([^"]*)"/', $html, $m);
    expect($m[1])->not->toBeEmpty();
    foreach ($m[1] as $class) {
        expect($class)->toContain('py-6.5')->not->toContain('py-2.5');
    }
});

test('status badges share one shape: tinted fill, no border', function () {
    foreach (SubOrderStatus::cases() as $status) {
        $html = Blade::render('<x-order-status-pill :status="$s" />', ['s' => $status]);
        expect($html)->not->toMatch('/\bborder\b/')->toMatch('/bg-[a-z-]*tint|bg-line/');
    }
    foreach (ReturnStatus::cases() as $status) {
        $html = Blade::render('<x-return-status-pill :status="$s" />', ['s' => $status]);
        expect($html)->not->toMatch('/\bborder\b/');
    }
    expect(Blade::render('<x-order-status-pill :status="$s" />', ['s' => SubOrderStatus::Shipped]))->toContain('bg-info-tint text-info');
});

test('switching tabs shows no spinner on the tabs', function () {
    $admin = g165Admin();
    $html = Livewire::actingAs($admin)->test(AdminSubscriptions::class)->html();
    $nav = substr($html, strpos($html, '<nav'), strpos($html, '</nav>') - strpos($html, '<nav'));

    expect($nav)->toContain("\$set('tab'")->not->toContain('animate-spin');
});

test('every admin and seller list uses the standard footer and rows-per-page', function () {
    // Card lists (not tables) keep the bare pager, which renders the same pagination view.
    $cardLists = ['seller/reviews/index.blade.php', 'seller/questions/index.blade.php'];

    foreach (['admin', 'seller'] as $area) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path("views/livewire/$area"))) as $file) {
            if (! str_ends_with($file, '.blade.php')) {
                continue;
            }
            $view = file_get_contents($file);
            $name = $area.'/'.str_replace('\\', '/', substr($file, strlen(resource_path("views/livewire/$area")) + 1));

            if (! in_array($name, $cardLists, true)) {
                expect(str_contains($view, '->links('))->toBeFalse("$name pages without x-ui.table.footer");
            }
            if (str_contains($view, 'x-ui.table.footer')) {
                expect(str_contains($view, 'x-ui.table.per-page'))->toBeTrue("$name has a footer but no rows-per-page");
            }
        }
    }
});
