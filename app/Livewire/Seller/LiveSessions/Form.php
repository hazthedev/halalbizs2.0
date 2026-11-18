<?php

namespace App\Livewire\Seller\LiveSessions;

use App\Livewire\Concerns\CurrentStore;
use App\Services\LiveSessionService;
use App\Support\DisplayTime;
use App\Support\LiveVideoUrl;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Seller live studio (M2.4): schedule a session, build its product rail, go
 * live, spotlight a product and end the stream. Store-scoped; presentation only.
 */
#[Layout('layouts.seller')]
class Form extends Component
{
    use CurrentStore;

    public string $title = '';

    public string $videoUrl = '';

    public string $voucherCode = '';

    public string $scheduledFor = '';

    public function mount(): void
    {
        abort_unless(config('live.enabled', true), 404);
    }

    public function save(LiveSessionService $live): void
    {
        $validated = $this->validate([
            'title' => 'required|string|max:120',
            'videoUrl' => ['nullable', 'url', 'max:500', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && LiveVideoUrl::parse($value) === null) {
                    $fail(__('Use a YouTube (watch, live or share link), Facebook video, Shopee Live, TikTok Live or Lazada Live link.'));
                }
            }],
            'voucherCode' => 'nullable|string|max:40',
            'scheduledFor' => 'nullable|date',
        ]);

        $live->create($this->currentStore(), [
            'title' => $validated['title'],
            'video_url' => $validated['videoUrl'] ?: null,
            'voucher_code' => $validated['voucherCode'] ? strtoupper($validated['voucherCode']) : null,
            'scheduled_for' => $validated['scheduledFor'] ? DisplayTime::parse($validated['scheduledFor']) : null,
        ]);

        session()->flash('status', __('Session created — add products to your rail.'));
        $this->redirectRoute('seller.live.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.seller.live-sessions.form', [
            'detectedPlatform' => LiveVideoUrl::parse($this->videoUrl)['platform'] ?? null,
        ])->title(__('New session'));
    }
}
