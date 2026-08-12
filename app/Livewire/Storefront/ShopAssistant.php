<?php

namespace App\Livewire\Storefront;

use App\Models\Product;
use App\Services\ConciergeService;
use App\Support\ClientIp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Global storefront overlay (M2.2): a bilingual AI shopping concierge. Holds
 * the chat transcript in component state, hands each turn to ConciergeService
 * (Claude tool-use, or a deterministic Scout fallback offline) and renders the
 * LIVE products it recommends. Touches no checkout path.
 */
class ShopAssistant extends Component
{
    /** send() throttling (AL-M1): unauthenticated + authenticated alike. */
    private const RATE_LIMIT_PER_MINUTE = 10;

    private const RATE_LIMIT_PER_HOUR = 60;

    /** History replayed into the LLM (AL-M1): cap turn count + per-turn length. */
    private const MAX_HISTORY_TURNS = 20;

    private const MAX_TURN_LENGTH = 2000;

    public bool $open = false;

    public string $draft = '';

    /** @var array<int, array{role: string, content: string, products?: array<int, int>}> */
    public array $history = [];

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function send(): void
    {
        $this->validate(['draft' => ['required', 'string', 'max:500']]);

        $message = trim($this->draft);

        if ($message === '') {
            return;
        }

        // Rate limit on the authenticated user id when present, else IP — this
        // component renders for guests on every storefront page, and each send()
        // can trigger up to MAX_TURNS+1 Anthropic API calls (AL-M1).
        $identifier = auth()->id() ?? ClientIp::bucket();
        $minuteKey = "concierge-send-minute:{$identifier}";
        $hourKey = "concierge-send-hour:{$identifier}";

        if (RateLimiter::tooManyAttempts($minuteKey, self::RATE_LIMIT_PER_MINUTE)
            || RateLimiter::tooManyAttempts($hourKey, self::RATE_LIMIT_PER_HOUR)) {
            $this->dispatch('toast', message: __("You're sending messages a little too fast — please wait a moment and try again."), type: 'error');

            return;
        }

        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($hourKey, 3600);

        // The client fully controls $history between requests ($wire.set), so it
        // cannot be trusted to be well-formed — sanitize shape/roles and clamp
        // both turn count and per-turn length before any of it is replayed to
        // the model (AL-M1).
        $this->history = $this->sanitizedHistory($this->history);

        // Prior turns (text only) become the model's conversation history.
        $priorTurns = collect($this->history)
            ->map(fn (array $turn) => ['role' => $turn['role'], 'content' => $turn['content']])
            ->all();

        $this->history[] = ['role' => 'user', 'content' => $message];
        $this->draft = '';

        $reply = app(ConciergeService::class)->reply($message, $priorTurns);

        $this->history[] = [
            'role' => 'assistant',
            'content' => $reply->text,
            'products' => $reply->products->modelKeys(),
        ];

        $this->history = $this->sanitizedHistory($this->history);
    }

    /**
     * Drop malformed turns (wrong shape, unexpected role) and clamp the
     * survivors to the last MAX_HISTORY_TURNS, each truncated to
     * MAX_TURN_LENGTH characters.
     *
     * @param  array<int, mixed>  $history
     * @return array<int, array{role: string, content: string, products?: array<int, int>}>
     */
    private function sanitizedHistory(array $history): array
    {
        $clean = collect($history)
            ->filter(fn ($turn) => is_array($turn)
                && in_array($turn['role'] ?? null, ['user', 'assistant'], true)
                && is_string($turn['content'] ?? null))
            ->map(function (array $turn) {
                $sanitized = [
                    'role' => $turn['role'],
                    'content' => mb_substr($turn['content'], 0, self::MAX_TURN_LENGTH),
                ];

                if ($turn['role'] === 'assistant' && is_array($turn['products'] ?? null)) {
                    $sanitized['products'] = array_values(array_map('intval', $turn['products']));
                }

                return $sanitized;
            })
            ->values()
            ->all();

        return array_slice($clean, -self::MAX_HISTORY_TURNS);
    }

    public function clearChat(): void
    {
        $this->history = [];
        $this->draft = '';
    }

    public function render()
    {
        // $history can carry a malformed/tampered shape the instant a client
        // issues a raw property update (no send() round-trip required), and
        // the view indexes ['role']/['content'] on every turn — sanitize
        // before the view (or the products lookup below) ever sees it.
        $this->history = $this->sanitizedHistory($this->history);

        $ids = collect($this->history)
            ->pluck('products')
            ->filter()
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        /** @var Collection<int, Product> $products */
        $products = $ids === []
            ? collect()
            : Product::query()->live()->with(['media', 'variants'])->whereIn('id', $ids)->get()->keyBy('id');

        return view('livewire.storefront.shop-assistant', [
            'products' => $products,
        ]);
    }
}
