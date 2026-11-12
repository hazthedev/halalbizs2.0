<?php

namespace App\Livewire\Storefront\Help;

use App\Models\HelpArticle;
use App\Services\ContentDrafts;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class Article extends Component
{
    public HelpArticle $article;

    public function mount(HelpArticle $article): void
    {
        $this->article = app(ContentDrafts::class)->find(HelpArticle::class, 'id', $article->id) ?? abort(404);
        $article = $this->article;

        // Count a view once per session, not per refresh; a staff preview is not a view.
        $sessionKey = "help_viewed.{$article->id}";

        if (! app(ContentDrafts::class)->previewing && ! session()->has($sessionKey)) {
            $article->increment('views');
            session()->put($sessionKey, true);
        }
    }

    public function render()
    {
        $related = app(ContentDrafts::class)->visible(HelpArticle::class)
            ->filter(fn (HelpArticle $other) => $other->category === $this->article->category && $other->id !== $this->article->id)
            ->sortBy(fn (HelpArticle $other) => [$other->position, $other->id])
            ->take(5)
            ->values();

        return view('livewire.storefront.help.article', [
            'related' => $related,
        ])->title($this->article->getTranslation('title', app()->getLocale()));
    }
}
