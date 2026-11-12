<?php

namespace App\Livewire\Admin\Content;

use App\Livewire\Concerns\ManagesDrafts;
use App\Models\Page;
use App\Services\ContentDrafts;
use App\Support\ContentLocales;
use App\Support\HtmlSanitizer;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * CMS pages (docs/08 §G) — language tabs, sanitized HTML body.
 * System pages keep their slugs locked (storefront routes/footer link them)
 * and can't be deleted; terms + privacy can't even be deactivated (the
 * checkout consent line links to them — a 404 there is a legal problem).
 */
#[Layout('layouts.admin')]
class Pages extends Component
{
    use ManagesDrafts;

    public const SYSTEM_SLUGS = ['about', 'trust-safety', 'terms', 'privacy', 'refund-policy', 'faq'];

    /** Legally required at all times — deactivation blocked. */
    public const ALWAYS_ACTIVE_SLUGS = ['terms', 'privacy'];

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $slug = '';

    /** @var array{en: string, ms: string, vi: string} */
    public array $title = ['en' => '', 'ms' => '', 'vi' => ''];

    /** @var array{en: string, ms: string, vi: string} */
    public array $body = ['en' => '', 'ms' => '', 'vi' => ''];

    public bool $isActive = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $pageId): void
    {
        // An open draft is what the editor carries on with.
        $page = app(ContentDrafts::class)->preview(Page::findOrFail($pageId));

        $this->resetForm();
        $this->editingId = $page->id;
        $this->slug = $page->slug;
        $this->title = ContentLocales::read($page, 'title');
        $this->body = ContentLocales::read($page, 'body');
        $this->isActive = $page->is_active;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $editing = $this->editingId !== null ? app(ContentDrafts::class)->preview(Page::findOrFail($this->editingId)) : null;
        $systemPage = $editing !== null && in_array($editing->slug, self::SYSTEM_SLUGS, true);

        $rules = [
            'title.en' => ['required', 'string', 'max:255'],
            'title.ms' => ['nullable', 'string', 'max:255'],
            'title.vi' => ['nullable', 'string', 'max:255'],
            'body.en' => ['required', 'string', 'max:65000'],
            'body.ms' => ['nullable', 'string', 'max:65000'],
            'body.vi' => ['nullable', 'string', 'max:65000'],
        ];

        if (! $systemPage) {
            $rules['slug'] = [
                'required', 'string', 'max:128', 'alpha_dash:ascii',
                Rule::unique('pages', 'slug')->ignore($this->editingId),
            ];
        }

        $this->validate($rules, attributes: [
            'title.en' => __('title (English)'),
            'body.en' => __('body (English)'),
        ]);

        $page = $editing ?? new Page;

        // Slug is LOCKED for system pages — never rewritten, whatever the input says.
        if (! $systemPage) {
            $page->slug = strtolower(trim($this->slug));
        }

        $page->is_active = in_array($page->slug, self::ALWAYS_ACTIVE_SLUGS, true) ? true : $this->isActive;

        ContentLocales::write($page, 'title', $this->title);
        ContentLocales::write($page, 'body', $this->body, transform: $this->sanitize(...));

        app(ContentDrafts::class)->stage($page, auth()->user());

        $this->dispatch('toast', message: __('Saved as a draft. It goes live when published.'));
        $this->resetForm();
    }

    public function toggleActive(int $pageId): void
    {
        $page = app(ContentDrafts::class)->preview(Page::findOrFail($pageId));

        if (in_array($page->slug, self::ALWAYS_ACTIVE_SLUGS, true)) {
            $this->dispatch('toast', message: __('Terms and privacy pages must stay published.'), type: 'error');

            return;
        }

        $page->is_active = ! $page->is_active;
        app(ContentDrafts::class)->stage($page, auth()->user());

        $this->dispatch('toast', message: __('Saved as a draft. It goes live when published.'));
    }

    public function delete(int $pageId): void
    {
        $this->authorize('cms.publish');

        $page = Page::findOrFail($pageId);

        if (in_array($page->slug, self::SYSTEM_SLUGS, true)) {
            $this->dispatch('toast', message: __('System pages can\'t be deleted.'), type: 'error');

            return;
        }

        $page->delete();

        if ($this->editingId === $pageId) {
            $this->resetForm();
        }

        $this->dispatch('toast', message: __('Page deleted'));
    }

    public function render()
    {
        return view('livewire.admin.content.pages', [
            'pages' => Page::orderBy('slug')->get(),
            'editingIsSystem' => $this->editingId !== null
                && in_array(Page::find($this->editingId)?->slug, self::SYSTEM_SLUGS, true),
        ])->title(__('Pages'));
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
        $this->reset(['showForm', 'editingId', 'slug', 'title', 'body', 'isActive']);
        $this->resetErrorBag();
    }
}
