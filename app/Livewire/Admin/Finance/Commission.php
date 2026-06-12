<?php

namespace App\Livewire\Admin\Finance;

use App\Models\Category;
use App\Models\Store;
use App\Services\CommissionResolver;
use App\Settings\CommissionSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Commission settings (docs/08 §F) — the global rate, read-only override
 * summaries (categories and stores edit their own rates on their own
 * screens), and the effective-rate tester. The tester runs the real
 * CommissionResolver, so it doubles as living documentation of the
 * hierarchy: store override → category chain upward → global default.
 */
#[Layout('layouts.admin')]
class Commission extends Component
{
    public string $globalRate = '';

    public string $testerStoreId = '';

    public string $testerCategoryId = '';

    public function mount(CommissionSettings $settings): void
    {
        $this->globalRate = self::formatRate($settings->global_rate);
    }

    public function saveGlobalRate(): void
    {
        $this->validate(
            ['globalRate' => ['required', 'numeric', 'min:0', 'max:100']],
            [],
            ['globalRate' => __('global rate')],
        );

        $settings = app(CommissionSettings::class);
        $settings->global_rate = round((float) $this->globalRate, 2);
        $settings->save();

        $this->globalRate = self::formatRate($settings->global_rate);

        $this->dispatch('toast', message: __('Global commission rate saved.'));
    }

    public function render()
    {
        return view('livewire.admin.finance.commission', [
            'categoryOverrides' => Category::query()->with('parent')->whereNotNull('commission_rate')->orderBy('position')->get(),
            'storeOverrides' => Store::query()->whereNotNull('commission_rate')->orderBy('name')->get(),
            'stores' => Store::query()->orderBy('name')->pluck('name', 'id'),
            'categories' => Category::query()->with('parent.parent')->get()
                ->sortBy(fn (Category $category) => $this->categoryChainLabel($category))
                ->mapWithKeys(fn (Category $category) => [$category->id => $this->categoryChainLabel($category)]),
            'tester' => $this->testerResult(),
        ])->title(__('Commission'));
    }

    /**
     * Resolve the tester pick through the real CommissionResolver and
     * explain which level of the hierarchy supplied the rate.
     *
     * @return array{rate: string, source: string}|null
     */
    private function testerResult(): ?array
    {
        $store = ctype_digit($this->testerStoreId) ? Store::query()->find((int) $this->testerStoreId) : null;

        if ($store === null) {
            return null;
        }

        $category = ctype_digit($this->testerCategoryId) ? Category::query()->find((int) $this->testerCategoryId) : null;

        $rate = app(CommissionResolver::class)->resolve($store, $category);

        // Breakdown mirrors CommissionResolver::resolve() exactly.
        if ($store->commission_rate !== null) {
            $source = __('Store override :rate%', ['rate' => self::formatRate((float) $store->commission_rate)]);
        } elseif (($node = $this->categoryRateSource($category)) !== null) {
            $source = __('Category chain: :name :rate%', [
                'name' => $node->getTranslation('name', app()->getLocale()),
                'rate' => self::formatRate((float) $node->commission_rate),
            ]);
        } else {
            $source = __('Global default :rate%', ['rate' => self::formatRate(app(CommissionSettings::class)->global_rate)]);
        }

        return ['rate' => self::formatRate($rate), 'source' => $source];
    }

    /** Walk up the category tree to the node that actually carries a rate. */
    private function categoryRateSource(?Category $category): ?Category
    {
        $node = $category;

        while ($node !== null && $node->commission_rate === null) {
            $node = $node->parent;
        }

        return $node;
    }

    /** "Electronics", "Home › Kitchen" — chain label for the tester select. */
    private function categoryChainLabel(Category $category): string
    {
        $names = [];
        $node = $category;

        while ($node !== null) {
            array_unshift($names, $node->getTranslation('name', app()->getLocale()));
            $node = $node->parent;
        }

        return implode(' › ', $names);
    }

    /** "5.00" → "5", "12.50" → "12.5" — rates read like sentences, not ledgers. */
    public static function formatRate(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }
}
