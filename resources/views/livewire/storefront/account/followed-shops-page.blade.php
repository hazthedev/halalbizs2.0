<x-account-shell active="following" :title="__('Followed shops')">
    @if ($stores->isNotEmpty())
        <ul class="divide-y divide-line rounded-[var(--radius-card)] border border-line bg-surface">
            @foreach ($stores as $store)
                <li class="flex items-center gap-3 p-4" wire:key="followed-store-{{ $store->id }}">
                    @if ($store->getFirstMediaUrl('logo'))
                        <img src="{{ $store->getFirstMediaUrl('logo') }}" alt="" class="size-12 shrink-0 rounded-full border border-line object-cover">
                    @else
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-full border border-line bg-brass-tint font-display text-lg text-brass-deep" aria-hidden="true">{{ mb_substr($store->name, 0, 1) }}</div>
                    @endif
                    <a href="{{ $store->storefrontUrl() }}" wire:navigate class="min-w-0 flex-1 truncate text-sm font-medium text-ink">{{ $store->name }}</a>
                    <button type="button" wire:click="unfollow({{ $store->id }})"
                            class="inline-flex min-h-11 shrink-0 items-center rounded-[var(--radius-control)] border border-line-strong px-4 text-sm font-medium text-ink-soft hover:text-ink">
                        {{ __('Following') }}
                    </button>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $stores->links() }}</div>
    @else
        <x-ui.empty-state :title="__('No followed shops yet')" :message="__('Follow a shop to see it here.')">
            <x-ui.button :href="route('home')">{{ __('Browse products') }}</x-ui.button>
        </x-ui.empty-state>
    @endif
</x-account-shell>
