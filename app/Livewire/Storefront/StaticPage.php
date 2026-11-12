<?php

namespace App\Livewire\Storefront;

use App\Models\Page;
use App\Services\ContentDrafts;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class StaticPage extends Component
{
    public Page $page;

    public function mount(string $slug): void
    {
        $this->page = app(ContentDrafts::class)->find(Page::class, 'slug', $slug) ?? abort(404);
    }

    public function render()
    {
        return view('livewire.storefront.static-page')
            ->title($this->page->seoTitle(app()->getLocale()));
    }
}
