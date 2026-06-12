<?php

namespace App\Livewire\Storefront\Help;

use App\Models\HelpArticle;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class Article extends Component
{
    public HelpArticle $article;

    public function mount(HelpArticle $article): void
    {
        abort_unless($article->is_active, 404);

        $this->article = $article;

        // Count a view once per session, not per refresh.
        $sessionKey = "help_viewed.{$article->id}";

        if (! session()->has($sessionKey)) {
            $article->increment('views');
            session()->put($sessionKey, true);
        }
    }

    public function render()
    {
        $related = HelpArticle::query()->active()
            ->where('category', $this->article->category)
            ->whereKeyNot($this->article->id)
            ->orderBy('position')
            ->orderBy('id')
            ->take(5)
            ->get();

        return view('livewire.storefront.help.article', [
            'related' => $related,
        ])->title($this->article->getTranslation('title', app()->getLocale()));
    }
}
