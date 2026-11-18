<?php

namespace App\Livewire\Admin\Support\Articles;

use App\Enums\HelpCategory;
use App\Models\HelpArticle;
use App\Services\ContentDrafts;
use App\Support\ContentLocales;
use App\Support\HtmlSanitizer;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class Form extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $category = 'buying';

    /** @var array{en: string, ms: string, vi: string} */
    public array $title = ['en' => '', 'ms' => '', 'vi' => ''];

    /** @var array{en: string, ms: string, vi: string} */
    public array $body = ['en' => '', 'ms' => '', 'vi' => ''];

    public string $position = '0';

    public bool $isActive = true;

    public function mount(?HelpArticle $article = null): void
    {
        if ($article === null || ! $article->exists) {
            return;
        }

        // An open draft is what the editor carries on with.
        $article = app(ContentDrafts::class)->preview(HelpArticle::findOrFail($article->id));

        $this->editingId = $article->id;
        $this->category = $article->category->value;
        $this->title = ContentLocales::read($article, 'title');
        $this->body = ContentLocales::read($article, 'body');
        $this->position = (string) $article->position;
        $this->isActive = $article->is_active;
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

        session()->flash('status', __('Saved as a draft. It goes live when published.'));
        $this->redirectRoute('admin.support.articles', navigate: true);
    }

    private function sanitize(string $html): string
    {
        // strip_tags() keeps every ATTRIBUTE on the tags it spares, so
        // onclick=/href="javascript:" survived into a {!! !!} body (C7).
        return HtmlSanitizer::clean($html, HtmlSanitizer::CMS_TAGS);
    }

    public function render()
    {
        return view('livewire.admin.support.articles.form', ['categories' => HelpCategory::cases()])->title(__('Help articles'));
    }
}
