<?php

namespace App\Livewire\Admin\Content;

use App\Models\Page;
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
    public const SYSTEM_SLUGS = ['about', 'terms', 'privacy', 'refund-policy', 'faq'];

    /** Legally required at all times — deactivation blocked. */
    public const ALWAYS_ACTIVE_SLUGS = ['terms', 'privacy'];

    public const ALLOWED_TAGS = '<p><br><h2><h3><ul><ol><li><strong><em><a>';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $slug = '';

    /** @var array{en: string, ms: string} */
    public array $title = ['en' => '', 'ms' => ''];

    /** @var array{en: string, ms: string} */
    public array $body = ['en' => '', 'ms' => ''];

    public bool $isActive = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $pageId): void
    {
        $page = Page::findOrFail($pageId);

        $this->resetForm();
        $this->editingId = $page->id;
        $this->slug = $page->slug;
        $this->title = [
            'en' => $page->getTranslation('title', 'en'),
            'ms' => $page->getTranslation('title', 'ms', false) ?? '',
        ];
        $this->body = [
            'en' => $page->getTranslation('body', 'en'),
            'ms' => $page->getTranslation('body', 'ms', false) ?? '',
        ];
        $this->isActive = $page->is_active;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $editing = $this->editingId !== null ? Page::findOrFail($this->editingId) : null;
        $systemPage = $editing !== null && in_array($editing->slug, self::SYSTEM_SLUGS, true);

        $rules = [
            'title.en' => ['required', 'string', 'max:255'],
            'title.ms' => ['nullable', 'string', 'max:255'],
            'body.en' => ['required', 'string', 'max:65000'],
            'body.ms' => ['nullable', 'string', 'max:65000'],
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

        // en is ALWAYS written (fallback locale); ms only when filled.
        $page->setTranslation('title', 'en', trim($this->title['en']));
        $page->setTranslation('body', 'en', $this->sanitize($this->body['en']));

        if (trim($this->title['ms'] ?? '') !== '') {
            $page->setTranslation('title', 'ms', trim($this->title['ms']));
        } else {
            $page->forgetTranslation('title', 'ms');
        }

        if (trim($this->body['ms'] ?? '') !== '') {
            $page->setTranslation('body', 'ms', $this->sanitize($this->body['ms']));
        } else {
            $page->forgetTranslation('body', 'ms');
        }

        $page->save();

        $this->dispatch('toast', message: $editing !== null ? __('Page updated') : __('Page created'));
        $this->resetForm();
    }

    public function toggleActive(int $pageId): void
    {
        $page = Page::findOrFail($pageId);

        if (in_array($page->slug, self::ALWAYS_ACTIVE_SLUGS, true)) {
            $this->dispatch('toast', message: __('Terms and privacy pages must stay published.'), type: 'error');

            return;
        }

        $page->update(['is_active' => ! $page->is_active]);

        $this->dispatch('toast', message: $page->is_active ? __('Page published') : __('Page unpublished'));
    }

    public function delete(int $pageId): void
    {
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
        return strip_tags(trim($html), self::ALLOWED_TAGS);
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'slug', 'title', 'body', 'isActive']);
        $this->resetErrorBag();
    }
}
