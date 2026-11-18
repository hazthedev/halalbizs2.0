<?php

namespace App\Livewire\Admin\Support;

use App\Livewire\Concerns\ManagesDrafts;
use App\Models\HelpArticle;
use App\Services\ContentDrafts;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Articles extends Component
{
    use ManagesDrafts;

    public function toggleActive(int $articleId): void
    {
        $article = app(ContentDrafts::class)->preview(HelpArticle::findOrFail($articleId));
        $article->is_active = ! $article->is_active;
        app(ContentDrafts::class)->stage($article, auth()->user());

        $this->dispatch('toast', message: __('Saved as a draft. It goes live when published.'));
    }

    public function delete(int $articleId): void
    {
        $this->authorize('cms.publish');

        HelpArticle::findOrFail($articleId)->delete();

        $this->dispatch('toast', message: __('Article deleted'));
    }

    public function render()
    {
        return view('livewire.admin.support.articles', [
            'articles' => HelpArticle::with('draft.media')->get()
                ->map(fn (HelpArticle $article) => app(ContentDrafts::class)->preview($article))
                ->sortBy(fn (HelpArticle $article) => [$article->category->value, $article->position, $article->id])
                ->values(),
        ])->title(__('Help articles'));
    }
}
