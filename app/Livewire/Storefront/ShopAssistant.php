<?php

namespace App\Livewire\Storefront;

use App\Models\Product;
use App\Services\ConciergeService;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Global storefront overlay (M2.2): a bilingual AI shopping concierge. Holds
 * the chat transcript in component state, hands each turn to ConciergeService
 * (Claude tool-use, or a deterministic Scout fallback offline) and renders the
 * LIVE products it recommends. Touches no checkout path.
 */
class ShopAssistant extends Component
{
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
    }

    public function clearChat(): void
    {
        $this->history = [];
        $this->draft = '';
    }

    public function render()
    {
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
