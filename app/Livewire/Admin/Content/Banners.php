<?php

namespace App\Livewire\Admin\Content;

use App\Livewire\Concerns\ManagesDrafts;
use App\Models\Banner;
use App\Services\ContentDrafts;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Banners extends Component
{
    use ManagesDrafts;

    public function toggleActive(int $bannerId): void
    {
        $banner = app(ContentDrafts::class)->preview(Banner::findOrFail($bannerId));
        $banner->is_active = ! $banner->is_active;
        app(ContentDrafts::class)->stage($banner, auth()->user());

        $this->dispatch('toast', message: __('Saved as a draft. It goes live when published.'));
    }

    public function move(int $bannerId, int $direction): void
    {
        $banners = $this->previewed()->values();
        $index = $banners->search(fn (Banner $banner) => $banner->id === $bannerId);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

        if ($index === false || $target < 0 || ! isset($banners[$target])) {
            return;
        }

        $ordered = $banners->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach (array_values($ordered) as $position => $banner) {
            if ((int) $banner->position !== $position) {
                $banner->position = $position;
                app(ContentDrafts::class)->stage($banner, auth()->user());
            }
        }
    }

    public function delete(int $bannerId): void
    {
        $this->authorize('cms.publish');

        Banner::findOrFail($bannerId)->delete();

        $this->dispatch('toast', message: __('Banner deleted'));
    }

    private function previewed(): Collection
    {
        $drafts = app(ContentDrafts::class);

        return Banner::with(['media', 'draft.media'])->get()
            ->map(fn (Banner $banner) => $banner->draft === null ? $banner : $drafts->preview($banner))
            ->sortBy(fn (Banner $banner) => [(int) $banner->position, $banner->id])
            ->values();
    }

    public function render()
    {
        return view('livewire.admin.content.banners', ['banners' => $this->previewed()])->title(__('Banners'));
    }
}
