<?php

namespace App\Livewire\Admin\Content;

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\ProductVariant;
use App\Support\RinggitInput;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin flash-sale management (docs/ROADMAP.md M1.2): create time-boxed sales
 * and add per-variant deal lines (promo price, allocation, per-buyer limit).
 * Money entered in RM, stored in sen.
 */
#[Layout('layouts.admin')]
class FlashSales extends Component
{
    // Create-sale form.
    public string $title = '';

    public string $startsAt = '';

    public string $endsAt = '';

    // Add-item form (per open sale).
    public ?int $addingToSaleId = null;

    public string $itemVariantId = '';

    public string $itemPromo = '';

    public int $itemAllocated = 10;

    public int $itemPerBuyer = 1;

    public function createSale(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:80'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
        ]);

        FlashSale::create([
            'title' => $data['title'],
            'starts_at' => $data['startsAt'],
            'ends_at' => $data['endsAt'],
            'is_active' => true,
        ]);

        $this->reset('title', 'startsAt', 'endsAt');
        $this->dispatch('toast', message: __('Flash sale created.'));
    }

    public function openAddItem(int $saleId): void
    {
        $this->addingToSaleId = $saleId;
        $this->reset('itemVariantId', 'itemPromo', 'itemAllocated', 'itemPerBuyer');
        $this->itemAllocated = 10;
        $this->itemPerBuyer = 1;
        $this->resetValidation();
    }

    public function addItem(): void
    {
        $this->validate([
            'itemVariantId' => ['required', Rule::exists('product_variants', 'id')],
            'itemPromo' => ['required', 'string'],
            'itemAllocated' => ['required', 'integer', 'min:1'],
            'itemPerBuyer' => ['required', 'integer', 'min:1'],
        ]);

        $sale = FlashSale::findOrFail((int) $this->addingToSaleId);

        FlashSaleItem::updateOrCreate(
            ['flash_sale_id' => $sale->id, 'product_variant_id' => (int) $this->itemVariantId],
            [
                'promo_price_sen' => (int) RinggitInput::toSen($this->itemPromo),
                'allocated_qty' => $this->itemAllocated,
                'per_buyer_limit' => $this->itemPerBuyer,
            ],
        );

        $this->addingToSaleId = null;
        $this->dispatch('toast', message: __('Deal added.'));
    }

    public function toggleActive(int $saleId): void
    {
        $sale = FlashSale::findOrFail($saleId);
        $sale->update(['is_active' => ! $sale->is_active]);
    }

    public function deleteSale(int $saleId): void
    {
        FlashSale::findOrFail($saleId)->delete();
        $this->dispatch('toast', message: __('Flash sale removed.'));
    }

    public function render(): View
    {
        return view('livewire.admin.content.flash-sales', [
            'sales' => FlashSale::query()
                ->with(['items.variant.product'])
                ->orderByDesc('starts_at')
                ->get(),
            'variantPick' => ProductVariant::query()
                ->with('product')
                ->latest('id')
                ->limit(50)
                ->get(),
        ])->title(__('Flash sales'));
    }
}
