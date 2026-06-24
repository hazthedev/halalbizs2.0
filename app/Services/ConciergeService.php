<?php

namespace App\Services;

use App\Models\Product;
use App\Services\Ai\ClaudeClient;
use App\Support\ConciergeReply;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bilingual EN/BM shopping concierge (M2.2). Recommends LIVE products only
 * (Hard Rule 5 — never reads historical order snapshots). When a Claude key is
 * configured it answers via tool-use over the existing Scout index; otherwise
 * it falls back to a deterministic Scout search so the feature works locally
 * and in tests with no network. Prices are always integer sen, formatted only
 * for display (Hard Rule 1).
 */
class ConciergeService
{
    /** Max tool-use round-trips before we force a closing answer. */
    private const MAX_TURNS = 4;

    private const MAX_RESULTS = 8;

    public function __construct(private ClaudeClient $claude) {}

    public function configured(): bool
    {
        return $this->claude->configured();
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history  prior turns (oldest first)
     */
    public function reply(string $message, array $history = [], ?string $locale = null): ConciergeReply
    {
        $locale = in_array($locale, ['en', 'ms'], true) ? $locale : app()->getLocale();
        $message = trim($message);

        if ($message === '') {
            return new ConciergeReply('', new EloquentCollection);
        }

        if ($this->claude->configured()) {
            try {
                return $this->viaClaude($message, $history, $locale);
            } catch (Throwable $e) {
                Log::warning('Concierge AI failed; using search fallback.', ['error' => $e->getMessage()]);
            }
        }

        return $this->fallback($message, $locale);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function viaClaude(string $message, array $history, string $locale): ConciergeReply
    {
        $messages = [];

        foreach ($history as $turn) {
            $role = $turn['role'] ?? '';
            $content = trim((string) ($turn['content'] ?? ''));

            if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $recommended = collect();

        for ($turn = 0; $turn < self::MAX_TURNS; $turn++) {
            $response = $this->claude->createMessage([
                'system' => $this->systemPrompt($locale),
                'messages' => $messages,
                'tools' => $this->tools(),
                'max_tokens' => 1024,
            ]);

            $messages[] = ['role' => 'assistant', 'content' => $response['content'] ?? []];

            if (($response['stop_reason'] ?? null) !== 'tool_use') {
                return new ConciergeReply($this->claude->text($response), $this->hydrate($recommended));
            }

            $toolResults = [];

            foreach ($response['content'] ?? [] as $block) {
                if (($block['type'] ?? null) !== 'tool_use') {
                    continue;
                }

                [$resultJson, $ids] = $this->runTool((string) ($block['name'] ?? ''), (array) ($block['input'] ?? []));
                $recommended = $recommended->merge($ids);

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content' => $resultJson,
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        // Out of tool turns — one final no-tools call for a closing answer.
        $final = $this->claude->createMessage([
            'system' => $this->systemPrompt($locale),
            'messages' => $messages,
            'max_tokens' => 1024,
        ]);

        return new ConciergeReply($this->claude->text($final), $this->hydrate($recommended));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: string, 1: array<int, int>} JSON result for Claude + recommended product ids
     */
    private function runTool(string $name, array $input): array
    {
        if ($name !== 'search_products') {
            return [json_encode(['error' => 'Unknown tool.']), []];
        }

        $query = trim((string) ($input['query'] ?? ''));
        $maxPriceSen = isset($input['max_price_rm']) ? max(0, (int) $input['max_price_rm']) * 100 : null;
        $minRating = isset($input['min_rating']) ? (int) $input['min_rating'] : null;

        $products = $this->search($query, $maxPriceSen, $minRating);

        if ($products->isEmpty()) {
            return [json_encode(['results' => [], 'note' => 'No live products matched — suggest the shopper refine the search.']), []];
        }

        $payload = $products->map(fn (Product $p) => $this->describe($p))->all();

        return [json_encode(['results' => $payload]), $products->modelKeys()];
    }

    /**
     * Scout keyword search → live products, relevance-ordered, optionally
     * filtered by price ceiling / minimum rating.
     *
     * @return EloquentCollection<int, Product>
     */
    private function search(string $query, ?int $maxPriceSen, ?int $minRating): EloquentCollection
    {
        if ($query === '') {
            return new EloquentCollection;
        }

        $ids = Product::search($query)->take(50)->keys()->all();

        if ($ids === []) {
            return new EloquentCollection;
        }

        $builder = Product::query()->live()->with(['media', 'variants', 'store'])->whereIn('id', $ids);

        if ($minRating !== null && $minRating >= 1 && $minRating <= 5) {
            $builder->where('rating_avg', '>=', $minRating);
        }

        if ($maxPriceSen !== null) {
            $builder->whereHas('variants', fn ($variants) => $variants->where('price_sen', '<=', $maxPriceSen));
        }

        $order = array_flip($ids);

        return $builder->get()
            ->sortBy(fn (Product $p) => $order[$p->id] ?? PHP_INT_MAX)
            ->take(self::MAX_RESULTS)
            ->values();
    }

    /**
     * Compact, snapshot-safe product description for the model. Price is a
     * formatted string from integer sen — never a float.
     *
     * @return array<string, mixed>
     */
    private function describe(Product $product): array
    {
        $minSen = $product->variants->isNotEmpty() ? $product->minPriceSen() : 0;

        return [
            'id' => $product->id,
            'name' => $product->getTranslation('name', 'en'),
            'price' => Money::format($minSen),
            'rating' => (string) $product->rating_avg,
            'sold' => (int) $product->sold_count,
            'store' => $product->store?->name,
            'url' => route('product.show', $product->slug),
        ];
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return EloquentCollection<int, Product>
     */
    private function hydrate(Collection $ids): EloquentCollection
    {
        $ids = $ids->map(fn ($id) => (int) $id)->unique()->take(self::MAX_RESULTS)->values();

        if ($ids->isEmpty()) {
            return new EloquentCollection;
        }

        $order = array_flip($ids->all());

        return Product::query()->live()->with(['media', 'variants', 'store'])
            ->whereIn('id', $ids->all())
            ->get()
            ->sortBy(fn (Product $p) => $order[$p->id] ?? PHP_INT_MAX)
            ->values();
    }

    private function fallback(string $message, string $locale): ConciergeReply
    {
        $products = $this->search($message, null, null);

        if ($products->isEmpty()) {
            return new ConciergeReply(
                $locale === 'ms'
                    ? 'Maaf, saya tidak menemui produk yang sepadan. Cuba kata kunci lain — contohnya jenama, kategori, atau ciri produk.'
                    : "I couldn't find a matching product. Try different keywords — a brand, a category, or a feature.",
                new EloquentCollection,
            );
        }

        return new ConciergeReply(
            $locale === 'ms'
                ? 'Berikut beberapa produk yang mungkin sesuai untuk anda:'
                : 'Here are a few products that might be a good fit:',
            $products,
        );
    }

    private function systemPrompt(string $locale): string
    {
        return <<<PROMPT
        You are the HalalBizs shopping concierge — a warm, concise, bilingual (English + Bahasa Melayu) assistant for a Malaysian halal-friendly multi-vendor marketplace.

        Rules:
        - Recommend ONLY products returned by the search_products tool. Never invent products, prices, stores or URLs.
        - Call search_products whenever the shopper is looking for something; you may search more than once to refine results.
        - Prices are in Malaysian Ringgit (RM) exactly as the tool returns them. Never alter, round or estimate a price.
        - Reply in the shopper's language. The interface language is "{$locale}" (en = English, ms = Bahasa Melayu); mirror the language the shopper writes in.
        - Be brief: a sentence or two, then let the product cards speak. Name the 1–3 most relevant items.
        - If nothing matches, say so honestly and suggest how to refine the search.
        - Stay on shopping topics for this marketplace.
        PROMPT;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tools(): array
    {
        return [[
            'name' => 'search_products',
            'description' => 'Search the live HalalBizs catalogue for products to recommend. Returns matching live products with name, price (RM), rating, units sold, store and a product URL. Use this for every recommendation — never invent products or prices.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Keywords: product name, brand, category or feature (English or Malay).'],
                    'max_price_rm' => ['type' => 'integer', 'description' => 'Optional maximum price in whole Ringgit (RM).'],
                    'min_rating' => ['type' => 'integer', 'description' => 'Optional minimum average rating, 1 to 5.'],
                ],
                'required' => ['query'],
            ],
        ]];
    }
}
