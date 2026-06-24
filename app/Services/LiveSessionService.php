<?php

namespace App\Services;

use App\Enums\LiveSessionStatus;
use App\Enums\PaymentStatus;
use App\Models\LiveSession;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Live-commerce sessions (M2.4). Lifecycle (scheduled → live → ended), the
 * product rail, the spotlight, and a read-only "just sold" feed over existing
 * orders. Touches no checkout/money code.
 */
class LiveSessionService
{
    public function enabled(): bool
    {
        return (bool) config('live.enabled', true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Store $store, array $attributes): LiveSession
    {
        return LiveSession::create([
            'store_id' => $store->id,
            'title' => $attributes['title'],
            'slug' => $this->uniqueSlug($attributes['title']),
            'status' => LiveSessionStatus::Scheduled,
            'video_url' => $attributes['video_url'] ?? null,
            'voucher_code' => $attributes['voucher_code'] ?? null,
            'scheduled_for' => $attributes['scheduled_for'] ?? null,
        ]);
    }

    public function goLive(LiveSession $session): void
    {
        if ($session->status === LiveSessionStatus::Ended) {
            return;
        }

        $session->update(['status' => LiveSessionStatus::Live, 'started_at' => $session->started_at ?? now()]);
    }

    public function end(LiveSession $session): void
    {
        $session->update(['status' => LiveSessionStatus::Ended, 'ended_at' => now()]);
    }

    /** Spotlight a product (must belong to the session's rail). */
    public function feature(LiveSession $session, Product $product): void
    {
        if ($session->products()->whereKey($product->id)->exists()) {
            $session->update(['featured_product_id' => $product->id]);
        }
    }

    public function addProduct(LiveSession $session, Product $product): void
    {
        if ($product->store_id !== $session->store_id) {
            return; // own-store products only
        }

        $session->products()->syncWithoutDetaching([
            $product->id => ['position' => $session->products()->count()],
        ]);
    }

    public function removeProduct(LiveSession $session, Product $product): void
    {
        $session->products()->detach($product->id);

        if ($session->featured_product_id === $product->id) {
            $session->update(['featured_product_id' => null]);
        }
    }

    /**
     * Recent paid purchases of this session's products — the live social proof
     * feed. Read-only; buyer names are masked.
     *
     * @return Collection<int, array{product: string, buyer: string, when: Carbon|null}>
     */
    public function recentlySold(LiveSession $session, int $limit = 8): Collection
    {
        $productIds = $session->products->pluck('id');

        if ($productIds->isEmpty()) {
            return collect();
        }

        return OrderItem::query()
            ->whereIn('product_id', $productIds)
            ->whereHas('subOrder.order', fn ($q) => $q->where('payment_status', PaymentStatus::Paid))
            ->with(['subOrder.order:id,user_id,paid_at', 'subOrder.order.user:id,name'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (OrderItem $item) => [
                'product' => $item->product_name,
                'buyer' => $this->maskName($item->subOrder?->order?->user?->name),
                'when' => $item->subOrder?->order?->paid_at,
            ]);
    }

    private function maskName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return __('A shopper');
        }

        $first = Str::of($name)->explode(' ')->first();

        return Str::limit($first, 1, '').'***';
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'live';
        $slug = $base;
        $i = 1;

        while (LiveSession::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
