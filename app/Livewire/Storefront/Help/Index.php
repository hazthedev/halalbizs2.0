<?php

namespace App\Livewire\Storefront\Help;

use App\Enums\HelpCategory;
use App\Models\HelpArticle;
use App\Services\ContentDrafts;
use App\Support\JsonSearch;
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
        $drafts = app(ContentDrafts::class);
        $query = HelpArticle::query()->active();

        $term = trim($this->search);

        if ($drafts->previewing) {
            // A preview searches the drafted words: in memory, over the few articles there are.
            $articles = $drafts->visible(HelpArticle::class)->filter(fn (HelpArticle $article) => $term === ''
                || collect([$locale, 'en'])->contains(fn ($lang) => mb_stripos($article->getTranslation('title', $lang, false).' '.$article->getTranslation('body', $lang, false), $term) !== false));
        } elseif ($term !== '') {
            // M-19: title/body are translatable JSON, so on MySQL the arrow
            // operator returns a BINARY-collated value and every lowercase
            // query matched nothing. This is the only search box on /help.
            $like = JsonSearch::pattern($term);

            $query->where(function (Builder $q) use ($like, $locale) {
                $q->whereRaw(JsonSearch::clause("title->{$locale}"), [$like])
                    ->orWhereRaw(JsonSearch::clause("body->{$locale}"), [$like]);

                if ($locale !== 'en') {
                    $q->orWhereRaw(JsonSearch::clause('title->en'), [$like])
                        ->orWhereRaw(JsonSearch::clause('body->en'), [$like]);
                }
            });
        }

        $articles ??= $query->orderBy('position')->orderBy('id')->get();

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
