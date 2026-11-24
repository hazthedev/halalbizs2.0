<?php

use App\Models\Affiliate;
use App\Models\Conversation;
use App\Models\HalalCertificate;
use App\Models\LiveSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;

// DB-10: MariaDB hands foreign keys back as strings; code that compares them
// with === needs ints. Cast on every model whose key the app compares strictly.
test('strictly compared foreign keys read back as int', function (string $model, array $columns) {
    $casts = (new $model)->getCasts();
    foreach ($columns as $column) {
        expect($casts[$column] ?? null)->toBe('integer', "$model.$column");
    }
})->with([
    [Store::class, ['user_id']],
    [Affiliate::class, ['user_id']],
    [Conversation::class, ['buyer_id', 'store_id']],
    [HalalCertificate::class, ['store_id']],
    [LiveSession::class, ['store_id', 'featured_product_id']],
    [Product::class, ['store_id', 'category_id']],
    [SubOrder::class, ['store_id']],
    [Order::class, ['user_id', 'affiliate_id', 'subscription_id']],
]);
