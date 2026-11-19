<?php

namespace App\Livewire\Storefront\Help;

use App\Support\SopGuide;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** HB-SOP: the buyer or seller guide, public so applicants can read it first. */
#[Layout('layouts.storefront')]
class Guide extends Component
{
    // Locked: it names a file on disk, so the browser never gets to change it.
    #[Locked]
    public string $guide;

    public function mount(string $guide): void
    {
        abort_unless(in_array($guide, SopGuide::PUBLIC, true), 404);
        $this->guide = $guide;
    }

    public function render()
    {
        $content = SopGuide::load($this->guide);

        return view('livewire.storefront.help.guide', ['content' => $content])->title($content['title']);
    }
}
