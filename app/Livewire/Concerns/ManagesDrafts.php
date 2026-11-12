<?php

namespace App\Livewire\Concerns;

use App\Services\ContentDrafts;
use Illuminate\Database\Eloquent\Model;

/**
 * Publish / throw away buttons shared by the content screens. Writing a
 * draft needs "cms.manage" (the route); making it live, discarding it or
 * deleting content needs "cms.publish".
 */
trait ManagesDrafts
{
    public function publishDraft(string $type, int $id): void
    {
        $this->authorize('cms.publish');

        app(ContentDrafts::class)->publish($this->draftSubject($type, $id), auth()->user());

        $this->dispatch('toast', message: __('Published. It is live now.'));
    }

    public function discardDraft(string $type, int $id): void
    {
        $this->authorize('cms.publish');

        app(ContentDrafts::class)->discard($this->draftSubject($type, $id));

        $this->dispatch('toast', message: __('Draft thrown away'));
    }

    private function draftSubject(string $type, int $id): Model
    {
        abort_unless(isset(ContentDrafts::SUBJECTS[$type]), 404);

        return ContentDrafts::SUBJECTS[$type]::findOrFail($id);
    }
}
