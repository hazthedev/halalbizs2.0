<?php

namespace App\Livewire\Admin\Content;

use App\Models\ContentVersion;
use App\Services\ContentDrafts;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * What went live for one piece of content (the last ContentDrafts::KEEP
 * publishes) and a way back: restore a version as a draft. A superadmin may
 * restore straight to live.
 */
#[Layout('layouts.admin')]
class History extends Component
{
    #[Locked]
    public string $type = '';

    #[Locked]
    public int $id = 0;

    public function mount(): void
    {
        $this->type = (string) request()->query('type', $this->type);
        $this->id = (int) request()->query('id', $this->id);
    }

    public function restore(int $versionId, bool $publish = false): void
    {
        abort_if($publish && ! auth()->user()->is_superadmin, 403);

        $subject = $this->subject();
        // Only a version of THIS item: the id comes from the browser.
        $version = $subject->versions()->find($versionId) ?? abort(404);

        app(ContentDrafts::class)->restore($version, auth()->user());

        if ($publish) {
            app(ContentDrafts::class)->publish($subject, auth()->user());
        }

        $this->dispatch('toast', message: $publish
            ? __('Restored. It is live now.')
            : __('Restored as a draft. It goes live when published.'));
    }

    public function render()
    {
        // Opened without an item (or one that is gone): an empty list, not an error page.
        $subject = isset(ContentDrafts::SUBJECTS[$this->type]) ? ContentDrafts::SUBJECTS[$this->type]::find($this->id) : null;
        $versions = $subject?->versions()->with('publisher')->get() ?? collect();

        return view('livewire.admin.content.history', [
            'name' => $subject === null ? '' : $this->name($subject),
            'hasDraft' => (bool) $subject?->draft()->exists(),
            // Newest first; each row lists the fields that differ from the version before it.
            'rows' => $versions->values()->map(fn (ContentVersion $version, int $index) => [
                'version' => $version,
                'changed' => isset($versions[$index + 1]) ? $this->changed($version->payload, $versions[$index + 1]->payload) : [],
            ]),
        ])->title(__('History'));
    }

    private function subject(): Model
    {
        abort_unless(isset(ContentDrafts::SUBJECTS[$this->type]), 404);

        return ContentDrafts::SUBJECTS[$this->type]::findOrFail($this->id);
    }

    private function name(Model $subject): string
    {
        return match ($this->type) {
            'theme' => __('Theme'),
            'section' => HomeSections::typeLabel($subject->type),
            default => (string) $subject->getTranslation('title', 'en'),
        };
    }

    /** @return array<int, string> field names whose stored value differs */
    private function changed(array $now, array $before): array
    {
        $plain = fn ($value) => is_string($value) && is_array($decoded = json_decode($value, true)) ? $decoded : $value;

        return collect($now)
            ->reject(fn ($value, $key) => str_starts_with($key, '_') || $plain($value) == $plain($before[$key] ?? null))
            ->keys()
            ->map(fn (string $key) => str_replace('_', ' ', $key))
            ->all();
    }
}
