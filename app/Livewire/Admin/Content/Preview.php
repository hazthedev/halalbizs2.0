<?php

namespace App\Livewire\Admin\Content;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Shows one storefront page with content drafts switched on, at web and
 * phone width. The page inside the frames is the real one (`?preview=1`,
 * see App\Http\Middleware\PreviewDrafts), so what staff check is what ships.
 */
#[Layout('layouts.admin')]
class Preview extends Component
{
    public function render()
    {
        $path = (string) request()->query('path', '/');

        // Only a path on this site: "/x", never "//host" or "https://host".
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            $path = '/';
        }

        return view('livewire.admin.content.preview', [
            'src' => url($path).(str_contains($path, '?') ? '&' : '?').'preview=1',
        ])->title(__('Preview'));
    }
}
