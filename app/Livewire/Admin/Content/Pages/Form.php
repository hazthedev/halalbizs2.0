<?php

namespace App\Livewire\Admin\Content\Pages;

use App\Livewire\Admin\Content\Pages;
use App\Models\Page;
use App\Services\ContentDrafts;
use App\Support\ContentLocales;
use App\Support\HtmlSanitizer;
use App\Support\UploadName;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class Form extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $editingId = null;

    public string $slug = '';

    /** @var array{en: string, ms: string, vi: string} */
    public array $title = ['en' => '', 'ms' => '', 'vi' => ''];

    /** @var array{en: string, ms: string, vi: string} */
    public array $body = ['en' => '', 'ms' => '', 'vi' => ''];

    public bool $isActive = true;

    /** Search engine / share title; empty = the page title. @var array{en: string, ms: string, vi: string} */
    public array $seoTitle = ['en' => '', 'ms' => '', 'vi' => ''];

    /** Search engine / share description; empty = the start of the page text. @var array{en: string, ms: string, vi: string} */
    public array $seoDescription = ['en' => '', 'ms' => '', 'vi' => ''];

    public ?TemporaryUploadedFile $shareImage = null;

    /** Custom pages only: a link in the website footer. */
    public bool $showInFooter = false;

    public function mount(?Page $page = null): void
    {
        if ($page === null || ! $page->exists) {
            return;
        }

        // An open draft is what the editor carries on with.
        $page = app(ContentDrafts::class)->preview(Page::findOrFail($page->id));

        $this->editingId = $page->id;
        $this->slug = $page->slug;
        $this->title = ContentLocales::read($page, 'title');
        $this->body = ContentLocales::read($page, 'body');
        $this->isActive = $page->is_active;
        $this->seoTitle = ContentLocales::read($page, 'seo_title');
        $this->seoDescription = ContentLocales::read($page, 'seo_description');
        $this->showInFooter = (bool) $page->show_in_footer;
    }

    public function save(): void
    {
        $editing = $this->editingId !== null ? app(ContentDrafts::class)->preview(Page::findOrFail($this->editingId)) : null;
        $systemPage = $editing !== null && in_array($editing->slug, Pages::SYSTEM_SLUGS, true);

        $rules = [
            'title.en' => ['required', 'string', 'max:255'],
            'title.ms' => ['nullable', 'string', 'max:255'],
            'title.vi' => ['nullable', 'string', 'max:255'],
            'body.en' => ['required', 'string', 'max:65000'],
            'body.ms' => ['nullable', 'string', 'max:65000'],
            'body.vi' => ['nullable', 'string', 'max:65000'],
            // Lengths search engines show in full.
            'seoTitle.*' => ['nullable', 'string', 'max:70'],
            'seoDescription.*' => ['nullable', 'string', 'max:200'],
            // Share cards are wide: 1200 x 630 is the usual size.
            'shareImage' => ['nullable', 'image', 'max:4096', 'dimensions:min_width=600,min_height=315'],
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

        $page->is_active = in_array($page->slug, Pages::ALWAYS_ACTIVE_SLUGS, true) ? true : $this->isActive;

        ContentLocales::write($page, 'title', $this->title);
        ContentLocales::write($page, 'body', $this->body, transform: $this->sanitize(...));
        ContentLocales::write($page, 'seo_title', $this->seoTitle, englishRequired: false);
        ContentLocales::write($page, 'seo_description', $this->seoDescription, englishRequired: false);

        // System pages already have their fixed place in the footer.
        $page->show_in_footer = ! in_array($page->slug, Pages::SYSTEM_SLUGS, true) && $this->showInFooter;

        $draft = app(ContentDrafts::class)->stage($page, auth()->user(), withFiles: $this->shareImage !== null);

        if ($this->shareImage !== null) {
            $draft->addMedia($this->shareImage->getRealPath())
                ->usingFileName(UploadName::safe($this->shareImage))
                ->toMediaCollection('share_image');
        }

        session()->flash('status', __('Saved as a draft. It goes live when published.'));
        $this->redirectRoute('admin.content.pages', navigate: true);
    }

    public function removeShareImage(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $page = app(ContentDrafts::class)->preview(Page::findOrFail($this->editingId));

        app(ContentDrafts::class)->stage($page, auth()->user(), clearMedia: ['share_image'])->clearMediaCollection('share_image');
        $this->shareImage = null;

        $this->dispatch('toast', message: __('Saved as a draft. It goes live when published.'));
    }

    private function sanitize(string $html): string
    {
        // strip_tags() keeps every ATTRIBUTE on the tags it spares, so
        // onclick=/href="javascript:" survived into a {!! !!} body (C7).
        return HtmlSanitizer::clean($html, HtmlSanitizer::CMS_TAGS);
    }

    public function render()
    {
        return view('livewire.admin.content.pages.form', [
            'editingShareImage' => $this->editingId !== null
                ? app(ContentDrafts::class)->preview(Page::findOrFail($this->editingId))->getFirstMediaUrl('share_image')
                : '',
            'editingIsSystem' => $this->editingId !== null
                && in_array(Page::find($this->editingId)?->slug, Pages::SYSTEM_SLUGS, true),
        ])->title(__('Pages'));
    }
}
