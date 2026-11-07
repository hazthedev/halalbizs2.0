<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buyer↔seller chat over ChatService (mobile plan §4), for both sides of a
 * thread: the caller is `buyer` on conversations they opened and `seller` on
 * conversations with the store they own. Anyone else gets a 404.
 */
class ConversationController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request): JsonResponse
    {
        $conversations = $this->visibleTo($request->user())
            ->with(['store.media', 'buyer', 'latestMessage.product.media'])
            ->withCount([
                'messages as unread_for_buyer' => fn ($query) => $query->whereNull('read_at')->where('sender_type', 'seller'),
                'messages as unread_for_seller' => fn ($query) => $query->whereNull('read_at')->where('sender_type', 'buyer'),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::ok($conversations->map(fn (Conversation $conversation) => $this->summary($conversation, $request->user()))->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer'],
            'product_id' => ['nullable', 'integer'],
        ]);

        $store = Store::query()->approved()->findOrFail($data['store_id']);
        $conversation = $this->chat->openConversation($request->user(), $store);

        $product = isset($data['product_id'])
            ? Product::query()->live()->where('store_id', $store->id)->with('media')->find($data['product_id'])
            : null;

        $summary = $this->summary($conversation->load(['store.media', 'buyer', 'latestMessage.product.media']), $request->user());

        return ApiResponse::ok(['product' => $this->productChip($product)] + $summary, null, 201);
    }

    /** The thread, newest message first (page 1 = latest); marks the caller's side read. */
    public function show(Request $request, int $conversation): JsonResponse
    {
        $row = $this->owned($request->user(), $conversation);
        $side = $this->sideFor($request->user(), $row);

        $this->chat->markRead($row, $side);

        $messages = Message::query()
            ->where('conversation_id', $row->id)
            ->with('product.media')
            ->latest('id')
            ->paginate(30);

        $messages->setCollection($messages->getCollection()->map(fn (Message $message) => $this->message($message)));

        return ApiResponse::ok($messages, [
            'conversation' => $this->summary($row->load(['store.media', 'buyer', 'latestMessage.product.media']), $request->user()),
        ]);
    }

    public function send(Request $request, int $conversation): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'product_id' => ['nullable', 'integer'],
        ], [
            'body.required' => __('Type a message first.'),
            'body.max' => __('Messages can be up to 2,000 characters.'),
        ]);

        $row = $this->owned($request->user(), $conversation);

        // Context chip only ever points at a product of THIS store (Messages::send).
        $context = isset($data['product_id'])
            ? Product::query()->where('store_id', $row->store_id)->find($data['product_id'])
            : null;

        $message = $this->chat->sendMessage($row, $this->sideFor($request->user(), $row), $request->user(), $data['body'], $context);

        return ApiResponse::ok($this->message($message->load('product.media')), null, 201);
    }

    private function visibleTo(User $user): Builder
    {
        $storeId = $user->store?->id;

        return Conversation::query()->where(fn (Builder $query) => $query
            ->where('buyer_id', $user->id)
            ->when($storeId !== null, fn (Builder $q) => $q->orWhere('store_id', $storeId)));
    }

    private function owned(User $user, int $id): Conversation
    {
        return $this->visibleTo($user)->findOrFail($id);
    }

    /** @return 'buyer'|'seller' */
    private function sideFor(User $user, Conversation $conversation): string
    {
        return $conversation->buyer_id === $user->id ? 'buyer' : 'seller';
    }

    /** @return array<string, mixed> */
    private function summary(Conversation $conversation, User $user): array
    {
        $side = $this->sideFor($user, $conversation);
        $last = $conversation->latestMessage;

        return [
            'id' => $conversation->id,
            'side' => $side,
            'store' => [
                'id' => $conversation->store->id,
                'slug' => $conversation->store->slug,
                'name' => $conversation->store->name,
                'logo' => $conversation->store->getFirstMediaUrl('logo', 'thumb') ?: null,
            ],
            'buyer' => ['name' => $conversation->buyer?->name],
            'last_message' => $last === null ? null : [
                'body' => $last->body,
                'at' => $last->created_at?->toIso8601String(),
                'side' => $last->sender_type,
            ],
            'unread_count' => (int) ($side === 'buyer'
                ? ($conversation->unread_for_buyer ?? $conversation->unreadCountFor('buyer'))
                : ($conversation->unread_for_seller ?? $conversation->unreadCountFor('seller'))),
            'product' => $this->productChip($last?->product),
        ];
    }

    /** @return array<string, mixed> */
    private function message(Message $message): array
    {
        return [
            'id' => $message->id,
            'side' => $message->sender_type,
            'body' => $message->body,
            'product' => $this->productChip($message->product),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function productChip(?Product $product): ?array
    {
        return $product === null ? null : [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->getTranslation('name', app()->getLocale()) ?: $product->getTranslation('name', 'en'),
            'thumb' => $product->getFirstMediaUrl('images', 'thumb') ?: null,
        ];
    }
}
