<div class="space-y-4">
    <x-ui.section-heading :title="$name === '' ? __('History') : __('History').': '.$name" as="h1" />
    <p class="text-[13px] text-ink-soft">
        {{ __('Every publish of this item, newest first. Restore brings a version back as a draft; shoppers see it only after it is published.') }}
        @if ($hasDraft)
            <span class="font-medium text-warn">{{ __('This item has a draft waiting. Restoring replaces that draft.') }}</span>
        @endif
    </p>

    <x-ui.card class="overflow-x-auto">
        @if ($rows->isEmpty())
            <x-ui.empty-state :title="__('No history yet')" :message="__('History starts with the first change published after drafts were introduced.')" />
        @else
            <table class="w-full min-w-[640px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Published') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('By') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('What changed') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php($version = $row['version'])
                        <tr class="border-b border-line last:border-b-0" wire:key="version-{{ $version->id }}">
                            <td class="whitespace-nowrap px-3 py-2">
                                {{ $version->published_at?->display()->format('d M Y, H:i') }}
                                @if ($loop->first)
                                    <x-ui.badge variant="verified" class="ml-1">{{ __('Live now') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $version->publisher?->name ?? ($version->published_by === null ? __('Before drafts were introduced') : __('Deleted account')) }}</td>
                            <td class="px-3 py-2 text-ink-soft">{{ $row['changed'] === [] ? '—' : implode(', ', $row['changed']) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    @unless ($loop->first)
                                        <button type="button" wire:click="restore({{ $version->id }})"
                                                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-emerald hover:bg-emerald-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Restore as draft') }}</button>
                                        @if (auth()->user()->is_superadmin)
                                            <button type="button" wire:click="restore({{ $version->id }}, true)"
                                                    wire:confirm="{{ __('Put this version live right now, without a draft?') }}"
                                                    class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Restore and publish') }}</button>
                                        @endif
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
