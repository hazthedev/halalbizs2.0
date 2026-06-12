<?php

namespace App\Livewire\Storefront;

use App\Models\Page;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class StaticPage extends Component
{
    public Page $page;

    public function mount(string $slug): void
    {
        $this->page = Page::active()->where('slug', $slug)->firstOrFail();
    }

    public function render()
    {
        return view('livewire.storefront.static-page')
            ->title($this->page->getTranslation('title', app()->getLocale()));
    }
}
