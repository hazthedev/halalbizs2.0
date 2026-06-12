<?php

namespace App\Livewire\Seller;

use Livewire\Attributes\Layout;
use Livewire\Component;

// Placeholder so routes boot - replaced by the real implementation.
#[Layout('layouts.seller')]
class Messages extends Component
{
    public function render()
    {
        return <<<'HTML'
        <div>Coming soon.</div>
        HTML;
    }
}
