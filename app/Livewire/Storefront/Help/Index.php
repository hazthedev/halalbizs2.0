<?php

namespace App\Livewire\Storefront\Help;

use App\Enums\HelpCategory;
use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public help centre — debounced search over active articles in the
 * current locale (en fallback), grouped by category.
 */
#[Layout('layouts.storefront')]
class Index extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    public function render()
    {
        $locale = app()->getLocale();
        $query = HelpArticle::query()->active();

        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.$term.'%';

            $query->where(function (Builder $q) use ($like, $locale) {
                $q->where("title->{$locale}", 'like', $like)
                    ->orWhere("body->{$locale}", 'like', $like);

                if ($locale !== 'en') {
                    $q->orWhere('title->en', 'like', $like)
                        ->orWhere('body->en', 'like', $like);
                }
            });
        }

        $articles = $query->orderBy('position')->orderBy('id')->get();

        $groups = collect(HelpCategory::cases())
            ->map(fn (HelpCategory $category) => [
                'category' => $category,
                'articles' => $articles->where('category', $category)->values(),
            ])
            ->filter(fn (array $group) => $group['articles']->isNotEmpty())
            ->values();

        return view('livewire.storefront.help.index', [
            'groups' => $groups,
            'searching' => $term !== '',
        ])->title(__('Help centre'));
    }
}
