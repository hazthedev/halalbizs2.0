<?php

namespace App\Livewire\Admin\Content;

use App\Livewire\Concerns\ManagesDrafts;
use App\Models\Page;
use App\Services\ContentDrafts;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Pages extends Component
{
    use ManagesDrafts;

    public const SYSTEM_SLUGS = ['about', 'trust-safety', 'terms', 'privacy', 'refund-policy', 'faq', 'delete-account'];

    public const ALWAYS_ACTIVE_SLUGS = ['terms', 'privacy', 'delete-account'];

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

        $this->dispatch('toast', message: __('Page deleted'));
    }

    public function render()
    {
        return view('livewire.admin.content.pages', [
            'pages' => Page::with('draft.media')->orderBy('slug')->get()->each(fn (Page $page) => app(ContentDrafts::class)->preview($page)),
        ])->title(__('Pages'));
    }
}
