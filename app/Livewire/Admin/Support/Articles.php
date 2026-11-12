<?php

namespace App\Livewire\Admin\Support;

use App\Enums\HelpCategory;
use App\Livewire\Concerns\ManagesDrafts;
use App\Models\HelpArticle;
use App\Services\ContentDrafts;
use App\Support\ContentLocales;
use App\Support\HtmlSanitizer;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Help-article CRUD — mirrors the Pages editor (EN/BM tabs, sanitized
 * HTML body) plus category, position, and a read-only views column.
 */
#[Layout('layouts.admin')]
class Articles extends Component
{
    use ManagesDrafts;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $category = 'buying';

    /** @var array{en: string, ms: string, vi: string} */
    public array $title = ['en' => '', 'ms' => '', 'vi' => ''];

    /** @var array{en: string, ms: string, vi: string} */
    public array $body = ['en' => '', 'ms' => '', 'vi' => ''];

    public string $position = '0';

    public bool $isActive = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $articleId): void
    {
        // An open draft is what the editor carries on with.
        $article = app(ContentDrafts::class)->preview(HelpArticle::findOrFail($articleId));

        $this->resetForm();
        $this->editingId = $article->id;
        $this->category = $article->category->value;
        $this->title = ContentLocales::read($article, 'title');
        $this->body = ContentLocales::read($article, 'body');
        $this->position = (string) $article->position;
        $this->isActive = $article->is_active;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->validate([
            'category' => ['required', Rule::enum(HelpCategory::class)],
            'title.en' => ['required', 'string', 'max:255'],
            'title.ms' => ['nullable', 'string', 'max:255'],
            'title.vi' => ['nullable', 'string', 'max:255'],
            'body.en' => ['required', 'string', 'max:65000'],
            'body.ms' => ['nullable', 'string', 'max:65000'],
            'body.vi' => ['nullable', 'string', 'max:65000'],
            'position' => ['required', 'integer', 'min:0', 'max:65535'],
        ], attributes: [
            'title.en' => __('title (English)'),
            'body.en' => __('body (English)'),
        ]);

        $article = $this->editingId !== null ? app(ContentDrafts::class)->preview(HelpArticle::findOrFail($this->editingId)) : new HelpArticle;

        $article->category = HelpCategory::from($this->category);
        $article->position = (int) $this->position;
        $article->is_active = $this->isActive;

        ContentLocales::write($article, 'title', $this->title);
        ContentLocales::write($article, 'body', $this->body, transform: $this->sanitize(...));

        app(ContentDrafts::class)->stage($article, auth()->user());

        $this->dispatch('toast', message: __('Saved as a draft. It goes live when published.'));
        $this->resetForm();
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

        if ($this->editingId === $articleId) {
            $this->resetForm();
        }

        $this->dispatch('toast', message: __('Article deleted'));
    }

    public function render()
    {
        return view('livewire.admin.support.articles', [
            'articles' => HelpArticle::with('draft')->get()
                ->map(fn (HelpArticle $article) => app(ContentDrafts::class)->preview($article))
                ->sortBy(fn (HelpArticle $article) => [$article->category->value, $article->position, $article->id])
                ->values(),
            'categories' => HelpCategory::cases(),
        ])->title(__('Help articles'));
    }

    /** Body is stored as HTML — strip everything outside the allowlist. */
    private function sanitize(string $html): string
    {
        // strip_tags() keeps every ATTRIBUTE on the tags it spares, so
        // onclick=/href="javascript:" survived into a {!! !!} body (C7).
        return HtmlSanitizer::clean($html, HtmlSanitizer::CMS_TAGS);
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'category', 'title', 'body', 'position', 'isActive']);
        $this->resetErrorBag();
    }
}
