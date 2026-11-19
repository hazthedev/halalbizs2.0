<?php

namespace App\Livewire\Admin\Support;

use App\Enums\HelpCategory;
use App\Livewire\Concerns\ManagesDrafts;
use App\Livewire\Concerns\WithTable;
use App\Models\HelpArticle;
use App\Services\ContentDrafts;
use App\Support\JsonSearch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin')]
class Articles extends Component
{
    use ManagesDrafts, WithTable;

    public const PER_PAGE = 20;

    #[Url(except: '')]
    public string $category = '';

    protected function tableFilters(): array
    {
        return ['category'];
    }

    protected function tableSorts(): array
    {
        return ['category' => 'category', 'title' => 'title->en', 'position' => 'position', 'views' => 'views', 'published' => 'is_active'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['category' => 'asc', 'position' => 'asc'];
    }

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
        $this->resetPage();

        $this->dispatch('toast', message: __('Article deleted'));
    }

    public function render()
    {
        return view('livewire.admin.support.articles', [
            'articles' => $this->sortTable(HelpArticle::with('draft.media')
                ->when(HelpCategory::tryFrom($this->category), fn ($q, $category) => $q->where('category', $category))
                ->when(trim($this->search) !== '', function ($q) {
                    $term = JsonSearch::pattern($this->search);
                    $q->where(fn ($q) => $q->whereRaw(JsonSearch::clause('title->en'), [$term])
                        ->orWhereRaw(JsonSearch::clause('title->ms'), [$term])
                        ->orWhereRaw(JsonSearch::clause('title->vi'), [$term]));
                }))
                ->paginate($this->tablePageSize())
                ->through(fn (HelpArticle $article) => app(ContentDrafts::class)->preview($article)),
            'categories' => HelpCategory::cases(),
        ])->title(__('Help articles'));
    }
}
