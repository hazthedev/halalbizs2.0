<?php

namespace App\Livewire\Admin;

use App\Support\SopGuide;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** HB-SOP: the admin guide. Any admin may read it, so no can: gate. */
#[Layout('layouts.admin')]
class Guide extends Component
{
    public function render()
    {
        $content = SopGuide::load('admin');

        return view('livewire.admin.guide', ['content' => $content])->title($content['title']);
    }
}
