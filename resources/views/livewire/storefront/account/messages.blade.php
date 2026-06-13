<x-account-shell active="messages" :title="__('Messages')">
    @php($active = $this->activeConversation)

    <div class="flex h-[600px] max-h-[72vh] overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
        {{-- ===== Thread list ===== --}}
        <div class="{{ $active ? 'hidden lg:flex' : 'flex' }} w-full flex-col border-line lg:w-72 lg:shrink-0 lg:border-r">
            @if ($this->conversations->isNotEmpty())
                <ul class="flex-1 divide-y divide-line overflow-y-auto">
                    @foreach ($this->conversations as $conversation)
                        <li wire:key="thread-{{ $conversation->id }}">
                            <button type="button"
                                    wire:click="openConversation({{ $conversation->id }})"
                                    class="flex min-h-11 w-full items-center gap-3 px-3.5 py-3 text-left transition-colors duration-150 hover:bg-paper {{ $conversationId === $conversation->id ? 'bg-paper' : '' }}">
                                @if ($conversation->store?->getFirstMediaUrl('logo', 'thumb'))
                                    <img src="{{ $conversation->store->getFirstMediaUrl('logo', 'thumb') }}"
                                         alt="{{ $conversation->store->name }}"
                                         class="size-10 shrink-0 rounded-full border border-line bg-paper object-cover">
                                @else
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full border border-line bg-paper font-display text-base font-bold text-ink-soft" aria-hidden="true">
                                        {{ mb_substr($conversation->store?->name ?? '?', 0, 1) }}
                                    </span>
                                @endif
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-baseline justify-between gap-2">
                                        <span class="truncate text-sm text-ink {{ $conversation->unread_count > 0 ? 'font-semibold' : 'font-medium' }}">{{ $conversation->store?->name }}</span>
                                        @if ($conversation->last_message_at)
                                            <span class="shrink-0 text-[11px] text-ink-faint">{{ $conversation->last_message_at->diffForHumans(short: true) }}</span>
                                        @endif
                                    </span>
                                    <span class="mt-0.5 flex items-center gap-2">
                                        <span class="min-w-0 flex-1 truncate text-xs text-ink-soft">{{ \Illuminate\Support\Str::limit($conversation->latestMessage?->body ?? __('No messages yet'), 48) }}</span>
                                        @if ($conversation->unread_count > 0)
                                            <span class="size-2 shrink-0 rounded-full bg-emerald" data-testid="thread-unread-dot" aria-hidden="true"></span>
                                            <span class="sr-only">{{ __('Unread messages') }}</span>
                                        @endif
                                    </span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="flex flex-1 flex-col items-center justify-center px-6 py-12 text-center">
                    <p class="font-display text-lg font-semibold">{{ __('No messages yet') }}</p>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Chats with sellers appear here. Start one from any product page.') }}</p>
                </div>
            @endif
        </div>

        {{-- ===== Conversation pane ===== --}}
        <div class="{{ $active ? 'flex' : 'hidden lg:flex' }} min-w-0 flex-1 flex-col">
            @if ($active)
                {{-- Pane header --}}
                <div class="flex items-center gap-2 border-b border-line px-3 py-2.5">
                    <button type="button" wire:click="closeConversation"
                            class="flex size-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] text-ink-soft transition-colors duration-150 hover:text-ink lg:hidden"
                            aria-label="{{ __('Back to all messages') }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                    </button>
                    @if ($active->store?->getFirstMediaUrl('logo', 'thumb'))
                        <img src="{{ $active->store->getFirstMediaUrl('logo', 'thumb') }}" alt="{{ $active->store->name }}"
                             class="size-9 shrink-0 rounded-full border border-line bg-paper object-cover">
                    @else
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full border border-line bg-paper font-display text-sm font-bold text-ink-soft" aria-hidden="true">
                            {{ mb_substr($active->store?->name ?? '?', 0, 1) }}
                        </span>
                    @endif
                    <a href="{{ $active->store?->storefrontUrl() }}" wire:navigate class="min-w-0 truncate text-sm font-semibold text-ink hover:text-emerald">
                        {{ $active->store?->name }}
                    </a>
                </div>

                {{-- Messages (polls only while this pane is open; marks incoming as read) --}}
                <div class="flex-1 space-y-3 overflow-y-auto bg-paper px-4 py-4"
                     wire:poll.5s="refreshThread"
                     x-data
                     x-init="$el.scrollTop = $el.scrollHeight;
                             new MutationObserver(() => { $el.scrollTop = $el.scrollHeight }).observe($el, { childList: true, subtree: true })">
                    @forelse ($active->messages as $chatMessage)
                        <x-chat-bubble :message="$chatMessage" own-side="buyer" wire:key="message-{{ $chatMessage->id }}" />
                    @empty
                        <p class="py-10 text-center text-sm text-ink-soft">{{ __('Say hello — ask about sizes, stock or shipping.') }}</p>
                    @endforelse
                </div>

                {{-- Composer --}}
                <form wire:submit="send" class="border-t border-line p-3">
                    @if ($contextProduct !== null)
                        <div class="mb-2 flex items-center gap-2 rounded-[var(--radius-card)] border border-line bg-paper px-2.5 py-1.5">
                            <span class="block size-9 shrink-0 overflow-hidden rounded-[var(--radius-control)] border border-line bg-surface">
                                @if ($contextProduct->getFirstMediaUrl('images', 'thumb'))
                                    <img src="{{ $contextProduct->getFirstMediaUrl('images', 'thumb') }}"
                                         alt="{{ $contextProduct->getTranslation('name', app()->getLocale()) }}"
                                         class="size-full object-cover">
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[11px] text-ink-faint">{{ __('Asking about') }}</span>
                                <span class="line-clamp-1 text-xs font-medium text-ink">{{ $contextProduct->getTranslation('name', app()->getLocale()) }}</span>
                            </span>
                            <button type="button" wire:click="removeContext"
                                    class="flex size-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] text-ink-soft transition-colors duration-150 hover:text-ink"
                                    aria-label="{{ __('Remove product from message') }}">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endif
                    <div class="flex items-end gap-2">
                        <label for="chat-body" class="sr-only">{{ __('Message') }}</label>
                        <textarea id="chat-body" rows="1" wire:model="body"
                                  x-data
                                  x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send(); }"
                                  placeholder="{{ __('Write a message…') }}"
                                  class="max-h-36 min-h-11 flex-1 resize-none rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2.5 text-sm text-ink placeholder:text-ink-faint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald"></textarea>
                        <button type="submit"
                                wire:loading.attr="disabled" wire:target="send"
                                class="flex size-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-emerald text-white transition-colors duration-150 hover:bg-emerald-deep active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50"
                                aria-label="{{ __('Send message') }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                        </button>
                    </div>
                    @error('body')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </form>
            @else
                <div class="hidden flex-1 flex-col items-center justify-center px-6 text-center lg:flex">
                    <p class="font-display text-lg font-semibold">{{ __('Pick a conversation') }}</p>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Select a chat on the left to read and reply.') }}</p>
                </div>
            @endif
        </div>
    </div>
</x-account-shell>
