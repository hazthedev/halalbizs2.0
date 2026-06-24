<?php

namespace App\Livewire\Seller\Products;

use App\Enums\ProductStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Models\Category;
use App\Models\Product;
use App\Support\Csv;
use App\Support\RinggitInput;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk product import (docs/ROADMAP.md M1.6). Sellers upload a CSV; each valid
 * row becomes a DRAFT product (+ default variant) for review before publishing.
 * Money is RM in the file → integer sen. A template download seeds the format.
 */
#[Layout('layouts.seller')]
class BulkImport extends Component
{
    use CurrentStore, WithFileUploads;

    public const COLUMNS = ['name_en', 'name_ms', 'description_en', 'category_id', 'price_rm', 'stock', 'sku'];

    public $csv;

    /** @var array{created: int, errors: array<int, string>}|null */
    public ?array $result = null;

    public function downloadTemplate(): StreamedResponse
    {
        return Csv::stream('product-import-template.csv', self::COLUMNS, [
            ['Cotton Tee', 'Baju Kapas', 'Soft everyday cotton tee.', '1', '39.90', '100', 'TEE-001'],
        ]);
    }

    public function import(): void
    {
        $this->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:4096']]);

        $store = $this->currentStore();
        $created = 0;
        $errors = [];

        $handle = fopen($this->csv->getRealPath(), 'r');
        $header = fgetcsv($handle, 0, ',', '"', '');
        $row = 1;

        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $row++;

            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }

            $data = @array_combine($header, array_pad($cells, count($header), null));
            $nameEn = trim((string) ($data['name_en'] ?? ''));
            $priceSen = RinggitInput::toSen($data['price_rm'] ?? null);
            $categoryId = (int) ($data['category_id'] ?? 0);

            if ($nameEn === '' || $priceSen === null || $priceSen <= 0) {
                $errors[] = __('Row :n: missing name or price.', ['n' => $row]);

                continue;
            }

            if (! Category::whereKey($categoryId)->exists()) {
                $errors[] = __('Row :n: unknown category id :id.', ['n' => $row, 'id' => $categoryId]);

                continue;
            }

            $product = Product::create([
                'store_id' => $store->id,
                'category_id' => $categoryId,
                'name' => ['en' => $nameEn, 'ms' => trim((string) ($data['name_ms'] ?? '')) ?: $nameEn],
                'description' => ['en' => trim((string) ($data['description_en'] ?? '')), 'ms' => ''],
                'condition' => 'new',
                'status' => ProductStatus::Draft, // never auto-publish
                'cod_enabled' => true,
            ]);

            $product->variants()->create([
                'sku' => trim((string) ($data['sku'] ?? '')) ?: null,
                'options_label' => null,
                'option_value_ids' => [],
                'price_sen' => $priceSen,
                'stock' => max(0, (int) ($data['stock'] ?? 0)),
                'is_default' => true,
                'position' => 0,
            ]);

            $created++;
        }

        fclose($handle);

        $this->result = ['created' => $created, 'errors' => $errors];
        $this->reset('csv');
        $this->dispatch('toast', message: __(':n products imported as drafts.', ['n' => $created]));
    }

    public function render(): View
    {
        return view('livewire.seller.products.bulk-import')->title(__('Bulk import'));
    }
}
