<?php

namespace App\Livewire\Storefront;

use App\Livewire\Concerns\InteractsWithCart;
use App\Services\HomeLayout;
use App\Settings\ThemeSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Component;

#[Layout('layouts.storefront')]
class Home extends Component
{
    use InteractsWithCart;

    /** Product ids hydrated from localStorage by Alpine (newest first). */
    public array $recentlyViewedIds = [];

    /** Called from the view's x-init with window.recentlyViewed.all(). */
    #[Renderless]
    public function loadRecentlyViewed(array $ids, ?int $sectionId = null): string
    {
        $this->recentlyViewedIds = collect($ids)
            ->filter(fn ($id) => is_int($id) || ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(12)
            ->values()
            ->all();

        $layout = app(HomeLayout::class);
        $section = $layout->sections()->first(fn ($row) => $row['section']->type === 'recently_viewed'
            && ($sectionId === null || $row['section']->id === $sectionId));

        if ($section === null) {
            return '';
        }

        return view('livewire.storefront.partials.recently-viewed', [
            'data' => $layout->recentlyViewedProducts($this->recentlyViewedIds),
            'title' => $section['section']->getTranslation('title', app()->getLocale()),
            'wishlistedIds' => $this->wishlistedIds(),
        ])->render();
    }

    public function render()
    {
        $layout = app(HomeLayout::class);
        $theme = app(ThemeSettings::class);

        return view('livewire.storefront.home', [
            'sections' => $layout->sections($this->recentlyViewedIds),
            'wishlistedIds' => $this->wishlistedIds(),
            'heroUrl' => $layout->heroUrl($theme),
            'occasion' => $theme->occasion,
        ]);
    }
}
