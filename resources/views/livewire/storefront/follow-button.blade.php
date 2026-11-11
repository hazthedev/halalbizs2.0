<div class="flex items-center gap-3" data-testid="follow-button">
    @unless ($own)
        <button type="button" wire:click="toggle" wire:loading.attr="disabled" wire:target="toggle"
                aria-pressed="{{ $following ? 'true' : 'false' }}"
                class="inline-flex min-h-11 shrink-0 items-center rounded-[var(--radius-control)] border px-4 text-sm font-medium transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $following ? 'border-line-strong bg-paper text-ink-soft' : 'border-ink bg-ink text-surface hover:opacity-90' }}">
            {{ $following ? __('Following') : __('Follow') }}
        </button>
    @endunless
    <span class="text-[13px] text-ink-soft tnum" data-testid="follower-count">{{ trans_choice('{1}:count follower|[0,*]:count followers', $followers, ['count' => number_format($followers)]) }}</span>
</div>
