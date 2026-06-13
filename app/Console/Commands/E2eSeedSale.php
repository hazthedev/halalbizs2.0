<?php

namespace App\Console\Commands;

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use Illuminate\Console\Command;

/**
 * Walks one COD order from the fixture store to completed for a buyer, so the
 * buyer/seller/admin dashboards have real spend, revenue and ledger data to
 * chart (local only). Run e2e:cod-fixture first.
 */
class E2eSeedSale extends Command
{
    protected $signature = 'e2e:seed-sale {email=buyer@halalbizs.test}';

    protected $description = 'Place and complete a COD sale so dashboards have data (local only)';

    public function handle(
        CartService $cart,
        CheckoutService $checkout,
        SubOrderStatusService $status,
        OrderService $orders,
    ): int {
        if (! app()->environment('local')) {
            return self::FAILURE;
        }

        $buyer = User::where('email', $this->argument('email'))->firstOrFail();
        $address = $buyer->addresses()->firstOrFail();

        $store = Store::where('name', 'COD Journey Store')->firstOrFail();
        $variant = Product::where('store_id', $store->id)->firstOrFail()->variants()->first();

        $cart->addItem($buyer, $variant, 1);
        $order = $checkout->place($buyer, $address, PaymentMethod::Cod);
        $subOrder = $order->subOrders->first();

        $status->transition($subOrder, SubOrderStatus::Processing, ActorType::Seller);
        $status->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
        $orders->markDelivered($subOrder->fresh(), ActorType::System);
        $orders->confirmReceived($subOrder->fresh(), $buyer->id);

        $this->info("Completed sale {$order->order_no} for {$buyer->email}.");

        return self::SUCCESS;
    }
}
