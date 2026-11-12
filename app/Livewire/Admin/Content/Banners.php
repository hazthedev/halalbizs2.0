<?php

namespace App\Livewire\Admin\Content;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Store;
use App\Support\ContentLocales;
use App\Support\DisplayTime;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Banner CRUD (docs/08 §G) — image, schedule window, position ordering.
 * Drives the storefront home `banner` section via Banner::active().
 */
#[Layout('layouts.admin')]
class Banners extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array{en: string, ms: string, vi: string} */
    public array $title = ['en' => '', 'ms' => '', 'vi' => ''];

    /** @var array{en: string, ms: string, vi: string} */
    public array $subtitle = ['en' => '', 'ms' => '', 'vi' => ''];

    /** @var array{en: string, ms: string, vi: string} */
    public array $ctaLabel = ['en' => '', 'ms' => '', 'vi' => ''];

    /**
     * Where the banner goes, picked from a list so the same choice opens on
     * the web and in the app (AppLinks). Stored as the web path in link_url.
     * none | product | store | category | page | flash_sale | help | search | custom
     */
    public string $destination = 'none';

    /** Slug of the picked product, store, category or page. */
    public string $destinationSlug = '';

    /** Search box for the product/store picker; the words for a search destination. */
    public string $destinationQuery = '';

    /** Only for destination "custom": a typed web address. */
    public string $linkUrl = '';

    /** Destinations that point at one record: type => web path prefix. */
    private const RECORD_PREFIXES = ['product' => '/p/', 'store' => '/s/', 'category' => '/c/', 'page' => '/page/'];

    /** Destinations with nothing to pick: type => web path. */
    private const FIXED_LINKS = ['flash_sale' => '/flash-sale', 'help' => '/help'];

    /** Guard rail: a banner never drops a shopper into the middle of paying. */
    private const BLOCKED_PATHS = '#^/(checkout|pay|payments|app/pay)(/|$)#';

    public string $startsAt = '';

    public string $endsAt = '';

    public bool $isActive = true;

    public ?TemporaryUploadedFile $image = null;

    /** Optional Vietnamese artwork when the headline is baked into the image. */
    public ?TemporaryUploadedFile $imageVi = null;

    /** Optional motion slide (mp4/webm, ≤30MB) — the image stays as fallback. */
    public ?TemporaryUploadedFile $video = null;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $bannerId): void
    {
        $banner = Banner::findOrFail($bannerId);

        $this->resetForm();
        $this->editingId = $banner->id;
        $this->title = ContentLocales::read($banner, 'title');
        $this->subtitle = ContentLocales::read($banner, 'subtitle');
        $this->ctaLabel = ContentLocales::read($banner, 'cta_label');
        $this->readDestination($banner->link_url);
        $this->startsAt = $banner->starts_at?->display()->format('Y-m-d\TH:i') ?? '';
        $this->endsAt = $banner->ends_at?->display()->format('Y-m-d\TH:i') ?? '';
        $this->isActive = $banner->is_active;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->validate([
            'title.en' => ['required', 'string', 'max:255'],
            'title.ms' => ['nullable', 'string', 'max:255'],
            'title.vi' => ['nullable', 'string', 'max:255'],
            'subtitle.en' => ['nullable', 'string', 'max:255'],
            'subtitle.ms' => ['nullable', 'string', 'max:255'],
            'subtitle.vi' => ['nullable', 'string', 'max:255'],
            'ctaLabel.en' => ['nullable', 'string', 'max:60'],
            'ctaLabel.ms' => ['nullable', 'string', 'max:60'],
            'ctaLabel.vi' => ['nullable', 'string', 'max:60'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
            'image' => [$this->editingId === null ? 'required' : 'nullable', 'image', 'max:4096'],
            'imageVi' => ['nullable', 'image', 'max:4096'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:30720'],
        ], [
            'video.mimetypes' => __('The video must be an MP4 or WebM file.'),
            'video.max' => __('The video must be 30MB or smaller.'),
        ], [
            'title.en' => __('title (English)'),
            'image' => __('banner image'),
            'video' => __('banner video'),
        ]);

        $link = $this->destinationLink();

        $starts = $this->parseDate($this->startsAt);
        $ends = $this->parseDate($this->endsAt);

        if ($starts !== null && $ends !== null && $ends->lte($starts)) {
            throw ValidationException::withMessages([
                'endsAt' => __('The banner must end after it starts.'),
            ]);
        }

        $banner = $this->editingId !== null
            ? Banner::findOrFail($this->editingId)
            : new Banner(['position' => ((int) Banner::max('position')) + 1]);

        $banner->fill([
            'link_url' => $link,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'is_active' => $this->isActive,
        ]);

        ContentLocales::write($banner, 'title', $this->title);
        ContentLocales::write($banner, 'subtitle', $this->subtitle, englishRequired: false);
        ContentLocales::write($banner, 'cta_label', $this->ctaLabel, englishRequired: false);

        $banner->save();

        if ($this->image !== null) {
            // singleFile collection — replaces the previous image.
            $banner->addMedia($this->image->getRealPath())
                ->usingFileName($this->image->getClientOriginalName())
                ->toMediaCollection('image');
        }

        if ($this->imageVi !== null) {
            $banner->addMedia($this->imageVi->getRealPath())
                ->usingFileName($this->imageVi->getClientOriginalName())
                ->toMediaCollection('image_vi');
        }

        if ($this->video !== null) {
            // singleFile collection — replaces the previous video.
            $banner->addMedia($this->video->getRealPath())
                ->usingFileName($this->video->getClientOriginalName())
                ->toMediaCollection('video');
        }

        $this->dispatch('toast', message: $this->editingId !== null ? __('Banner updated') : __('Banner created'));
        $this->resetForm();
    }

    /** Drops the motion slide — the banner falls back to its image. */
    public function removeVideo(): void
    {
        if ($this->editingId === null) {
            return;
        }

        Banner::findOrFail($this->editingId)->clearMediaCollection('video');

        $this->dispatch('toast', message: __('Banner video removed'));
    }

    public function removeVietnameseImage(): void
    {
        if ($this->editingId === null) {
            $this->imageVi = null;

            return;
        }

        Banner::findOrFail($this->editingId)->clearMediaCollection('image_vi');
        $this->imageVi = null;
        $this->dispatch('toast', message: __('Vietnamese banner image removed'));
    }

    public function toggleActive(int $bannerId): void
    {
        $banner = Banner::findOrFail($bannerId);
        $banner->update(['is_active' => ! $banner->is_active]);

        $this->dispatch('toast', message: $banner->is_active ? __('Banner enabled') : __('Banner disabled'));
    }

    /** Swap with the neighbour, then re-index positions 0..n. */
    public function move(int $bannerId, int $direction): void
    {
        $banners = Banner::orderBy('position')->orderBy('id')->get()->values();
        $index = $banners->search(fn (Banner $banner) => $banner->id === $bannerId);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

        if ($index === false || $target < 0 || ! isset($banners[$target])) {
            return;
        }

        $ordered = $banners->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach (array_values($ordered) as $position => $banner) {
            if ($banner->position !== $position) {
                $banner->update(['position' => $position]);
            }
        }
    }

    public function delete(int $bannerId): void
    {
        Banner::findOrFail($bannerId)->delete();

        if ($this->editingId === $bannerId) {
            $this->resetForm();
        }

        $this->dispatch('toast', message: __('Banner deleted'));
    }

    /** Switching the list clears what was picked for the previous choice. */
    public function updatedDestination(): void
    {
        $this->reset(['destinationSlug', 'destinationQuery']);
        $this->resetErrorBag(['destinationSlug', 'destinationQuery', 'linkUrl']);
    }

    /** Typing in the picker again drops the earlier pick. */
    public function updatedDestinationQuery(): void
    {
        if (in_array($this->destination, ['product', 'store'], true)) {
            $this->destinationSlug = '';
        }
    }

    /**
     * Up to 8 live products / approved stores whose name contains the typed words.
     *
     * @return array<string, string> slug => name
     */
    public function destinationMatches(): array
    {
        $words = mb_strtolower(trim($this->destinationQuery));

        if (! in_array($this->destination, ['product', 'store'], true) || mb_strlen($words) < 2 || $this->destinationSlug !== '') {
            return [];
        }

        $query = $this->destination === 'product' ? Product::live() : Store::approved();

        // ponytail: product names are a JSON column, so this matches any language (and the
        // keys "en"/"ms"/"vi"). Fine for a picker; use Scout if it ever needs ranking.
        return $query->whereRaw('LOWER(name) LIKE ?', ['%'.addcslashes($words, '%_\\').'%'])
            ->orderBy('id')
            ->take(8)
            ->get()
            ->mapWithKeys(fn (Model $record) => [$record->slug => $this->recordName($record)])
            ->all();
    }

    public function chooseDestination(string $slug): void
    {
        $record = $this->destinationRecord($this->destination, $slug);

        if ($record === null) {
            return;
        }

        $this->destinationSlug = $record->slug;
        $this->destinationQuery = $this->recordName($record);
        $this->resetErrorBag('destinationSlug');
    }

    public function render()
    {
        return view('livewire.admin.content.banners', [
            'destinationMatches' => $this->destinationMatches(),
            'categoryOptions' => $this->destination === 'category'
                ? Category::active()->orderBy('position')->get()->mapWithKeys(fn (Category $c) => [$c->slug => $c->getTranslation('name', 'en')])->all()
                : [],
            'pageOptions' => $this->destination === 'page'
                ? Page::active()->orderBy('id')->get()->mapWithKeys(fn (Page $p) => [$p->slug => $p->getTranslation('title', 'en')])->all()
                : [],
            'banners' => Banner::with('media')->orderBy('position')->orderBy('id')->get(),
        ])->title(__('Banners'));
    }

    /** The web path this form's destination stands for; null = no link. */
    private function destinationLink(): ?string
    {
        if (isset(self::FIXED_LINKS[$this->destination])) {
            return self::FIXED_LINKS[$this->destination];
        }

        if (isset(self::RECORD_PREFIXES[$this->destination])) {
            $record = $this->destinationRecord($this->destination, $this->destinationSlug);

            if ($record === null) {
                throw ValidationException::withMessages([
                    'destinationSlug' => __('Pick one from the list. It must be live on the site.'),
                ]);
            }

            return self::RECORD_PREFIXES[$this->destination].$record->slug;
        }

        if ($this->destination === 'search') {
            $words = trim($this->destinationQuery);

            if ($words === '' || mb_strlen($words) > 80) {
                throw ValidationException::withMessages([
                    'destinationQuery' => __('Type the words to search for (80 characters at most).'),
                ]);
            }

            return '/search?q='.rawurlencode($words);
        }

        if ($this->destination !== 'custom' || trim($this->linkUrl) === '') {
            return null;
        }

        $url = trim($this->linkUrl);

        // M-17: this lands in a live <a href> on the storefront hero, so a
        // cms.manage admin could otherwise plant `javascript:`. Blade escapes
        // the quotes but not the scheme. Relative paths must stay legal:
        // home.blade.php branches on them for wire:navigate.
        if (mb_strlen($url) > 255 || ! HtmlSanitizer::isSafeUrl($url)) {
            throw ValidationException::withMessages([
                'linkUrl' => __('Use a full https:// address or a path starting with /.'),
            ]);
        }

        if (preg_match(self::BLOCKED_PATHS, (string) parse_url($url, PHP_URL_PATH)) === 1) {
            throw ValidationException::withMessages([
                'linkUrl' => __('A banner cannot open checkout or payment pages.'),
            ]);
        }

        return $url;
    }

    /** Reads a saved link back into the picker; anything it cannot place is a typed web address. */
    private function readDestination(?string $link): void
    {
        $link = trim((string) $link);

        if ($link === '') {
            $this->destination = 'none';

            return;
        }

        if (($type = array_search($link, self::FIXED_LINKS, true)) !== false) {
            $this->destination = $type;

            return;
        }

        if (preg_match('#^/search\?q=([^&]+)$#', $link, $m) === 1) {
            $this->destination = 'search';
            $this->destinationQuery = rawurldecode($m[1]);

            return;
        }

        foreach (self::RECORD_PREFIXES as $type => $prefix) {
            $record = str_starts_with($link, $prefix)
                ? $this->destinationRecord($type, substr($link, strlen($prefix)))
                : null;

            if ($record !== null) {
                $this->destination = $type;
                $this->destinationSlug = $record->slug;
                $this->destinationQuery = in_array($type, ['product', 'store'], true) ? $this->recordName($record) : '';

                return;
            }
        }

        $this->destination = 'custom';
        $this->linkUrl = $link;
    }

    /** The live record a destination points at, or null when it is gone or hidden. */
    private function destinationRecord(string $type, string $slug): ?Model
    {
        if ($slug === '') {
            return null;
        }

        return match ($type) {
            'product' => Product::live()->where('slug', $slug)->first(),
            'store' => Store::approved()->where('slug', $slug)->first(),
            'category' => Category::active()->where('slug', $slug)->first(),
            'page' => Page::active()->where('slug', $slug)->first(),
            default => null,
        };
    }

    private function recordName(Model $record): string
    {
        return $record instanceof Product ? $record->getTranslation('name', 'en') : (string) $record->name;
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'title', 'subtitle', 'ctaLabel', 'destination', 'destinationSlug', 'destinationQuery', 'linkUrl', 'startsAt', 'endsAt', 'isActive', 'image', 'imageVi', 'video']);
        $this->resetErrorBag();
    }

    private function parseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return DisplayTime::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
