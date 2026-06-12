<?php

namespace App\Livewire\Admin\Support;

use App\Enums\HelpCategory;
use App\Models\HelpArticle;
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
    public const ALLOWED_TAGS = '<p><br><h2><h3><ul><ol><li><strong><em><a>';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $category = 'buying';

    /** @var array{en: string, ms: string} */
    public array $title = ['en' => '', 'ms' => ''];

    /** @var array{en: string, ms: string} */
    public array $body = ['en' => '', 'ms' => ''];

    public string $position = '0';

    public bool $isActive = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $articleId): void
    {
        $article = HelpArticle::findOrFail($articleId);

        $this->resetForm();
        $this->editingId = $article->id;
        $this->category = $article->category->value;
        $this->title = [
            'en' => $article->getTranslation('title', 'en'),
            'ms' => $article->getTranslation('title', 'ms', false) ?? '',
        ];
        $this->body = [
            'en' => $article->getTranslation('body', 'en'),
            'ms' => $article->getTranslation('body', 'ms', false) ?? '',
        ];
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
            'body.en' => ['required', 'string', 'max:65000'],
            'body.ms' => ['nullable', 'string', 'max:65000'],
            'position' => ['required', 'integer', 'min:0', 'max:65535'],
        ], attributes: [
            'title.en' => __('title (English)'),
            'body.en' => __('body (English)'),
        ]);

        $article = $this->editingId !== null ? HelpArticle::findOrFail($this->editingId) : new HelpArticle;

        $article->category = HelpCategory::from($this->category);
        $article->position = (int) $this->position;
        $article->is_active = $this->isActive;

        // en is ALWAYS written (fallback locale); ms only when filled.
        $article->setTranslation('title', 'en', trim($this->title['en']));
        $article->setTranslation('body', 'en', $this->sanitize($this->body['en']));

        if (trim($this->title['ms'] ?? '') !== '') {
            $article->setTranslation('title', 'ms', trim($this->title['ms']));
        } else {
            $article->forgetTranslation('title', 'ms');
        }

        if (trim($this->body['ms'] ?? '') !== '') {
            $article->setTranslation('body', 'ms', $this->sanitize($this->body['ms']));
        } else {
            $article->forgetTranslation('body', 'ms');
        }

        $article->save();

        $this->dispatch('toast', message: $this->editingId !== null ? __('Article updated') : __('Article created'));
        $this->resetForm();
    }

    public function toggleActive(int $articleId): void
    {
        $article = HelpArticle::findOrFail($articleId);
        $article->update(['is_active' => ! $article->is_active]);

        $this->dispatch('toast', message: $article->is_active ? __('Article published') : __('Article unpublished'));
    }

    public function delete(int $articleId): void
    {
        HelpArticle::findOrFail($articleId)->delete();

        if ($this->editingId === $articleId) {
            $this->resetForm();
        }

        $this->dispatch('toast', message: __('Article deleted'));
    }

    public function render()
    {
        return view('livewire.admin.support.articles', [
            'articles' => HelpArticle::orderBy('category')->orderBy('position')->orderBy('id')->get(),
            'categories' => HelpCategory::cases(),
        ])->title(__('Help articles'));
    }

    /** Body is stored as HTML — strip everything outside the allowlist. */
    private function sanitize(string $html): string
    {
        return strip_tags(trim($html), self::ALLOWED_TAGS);
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'category', 'title', 'body', 'position', 'isActive']);
        $this->resetErrorBag();
    }
}
