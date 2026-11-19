<div class="space-y-4">

    <x-ui.section-heading :title="__('Banners')" as="h1">
        <x-slot:actions>
            <x-ui.button icon="plus" :href="route('admin.content.banners.create')">
                {{ __('Add banner') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.section-heading>

    @if (session('status'))
        <div role="status" class="rounded-[var(--radius-control)] bg-emerald-tint px-4 py-3 text-sm text-emerald-deep">{{ session('status') }}</div>
    @endif

    <x-ui.card class="overflow-x-auto">
        @if ($banners->isEmpty())
            <x-ui.empty-state :title="__('No banners yet')" :message="__('Banners appear in the storefront home carousel as soon as you add one.')" />
        @else
            <table class="w-full min-w-[760px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Order') }}</th>
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Banner') }}</th>
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Link') }}</th>
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Schedule') }}</th>
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Active') }}</th>
                        <th scope="col" class="px-3 py-6.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($banners as $banner)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="banner-{{ $banner->id }}">
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-0.5">
                                    <button type="button" wire:click="move({{ $banner->id }}, -1)" @disabled($loop->first)
                                            aria-label="{{ __('Move up') }}"
                                            class="inline-flex size-11 items-center justify-center rounded-[var(--radius-control)] text-ink-soft hover:text-ink disabled:opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                                    </button>
                                    <button type="button" wire:click="move({{ $banner->id }}, 1)" @disabled($loop->last)
                                            aria-label="{{ __('Move down') }}"
                                            class="inline-flex size-11 items-center justify-center rounded-[var(--radius-control)] text-ink-soft hover:text-ink disabled:opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-3">
                                    @if ($url = $banner->getFirstMediaUrl('image'))
                                        <img src="{{ $url }}" alt="{{ $banner->getTranslation('title', 'en') }}" class="h-10 w-20 shrink-0 rounded-[var(--radius-control)] border border-line bg-paper object-cover">
                                    @else
                                        <span class="flex h-10 w-20 shrink-0 items-center justify-center rounded-[var(--radius-control)] border border-line bg-paper text-ink-faint">—</span>
                                    @endif
                                    <span class="font-medium text-ink">{{ $banner->getTranslation('title', 'en') }}</span>
                                    @if ($banner->draft)
                                        <x-ui.badge variant="warn">{{ __('Draft') }}</x-ui.badge>
                                    @endif
                                </div>
                            </td>
                            <td class="max-w-44 truncate px-3 py-2 font-mono text-[12px] text-ink-soft">{{ $banner->link_url ?? '—' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">
                                @if ($banner->starts_at === null && $banner->ends_at === null)
                                    {{ __('Always on') }}
                                @else
                                    {{ $banner->starts_at?->display()->format('d M Y H:i') ?? __('Now') }} → {{ $banner->ends_at?->display()->format('d M Y H:i') ?? __('No end') }}
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <button type="button" role="switch" aria-checked="{{ $banner->is_active ? 'true' : 'false' }}"
                                        wire:click="toggleActive({{ $banner->id }})"
                                        aria-label="{{ __('Toggle :title', ['title' => $banner->getTranslation('title', 'en')]) }}"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius-control)] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $banner->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                        <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $banner->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.draft-actions type="banner" :id="$banner->id" :draft="$banner->draft" preview-path="/" />
                                    <x-ui.button variant="ghost" :href="route('admin.content.banners.edit', $banner)">{{ __('Edit') }}</x-ui.button>
                                    @can('cms.publish')
                                        <button type="button" wire:click="delete({{ $banner->id }})"
                                                wire:confirm="{{ __('Delete this banner? This cannot be undone.') }}"
                                                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Delete') }}</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
