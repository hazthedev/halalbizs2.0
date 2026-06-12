@props(['message', 'ownSide'])

@php($own = $message->sender_type === $ownSide)

<div {{ $attributes->merge(['class' => 'flex '.($own ? 'justify-end' : 'justify-start')]) }}>
    <div class="max-w-[80%] sm:max-w-[70%]">
        {{-- Product context chip ("asking about this item") --}}
        @if ($message->product !== null)
            <a href="{{ route('product.show', $message->product->slug) }}" wire:navigate
               data-testid="chat-context-chip"
               class="mb-1 flex min-h-11 items-center gap-2 rounded-[10px] border border-line bg-surface px-2.5 py-1.5 transition-colors duration-150 hover:border-ink">
                <span class="block size-9 shrink-0 overflow-hidden rounded-lg border border-line bg-paper">
                    @if ($message->product->getFirstMediaUrl('images', 'thumb'))
                        <img src="{{ $message->product->getFirstMediaUrl('images', 'thumb') }}"
                             alt="{{ $message->product->getTranslation('name', app()->getLocale()) }}"
                             class="size-full object-cover" loading="lazy">
                    @endif
                </span>
                <span class="line-clamp-2 min-w-0 text-xs font-medium text-ink">
                    {{ $message->product->getTranslation('name', app()->getLocale()) }}
                </span>
            </a>
        @endif

        <div class="whitespace-pre-line break-words rounded-[10px] px-3.5 py-2.5 text-sm leading-relaxed text-ink {{ $own ? 'bg-emerald-tint' : 'border border-line bg-surface' }}">{{ $message->body }}</div>
        <p class="mt-1 text-[11px] text-ink-faint {{ $own ? 'text-right' : '' }}">
            {{ $message->created_at->format('j M, g:i a') }}
        </p>
    </div>
</div>
