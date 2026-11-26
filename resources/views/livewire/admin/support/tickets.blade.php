@php
    use App\Enums\TicketPriority;
    use App\Enums\TicketStatus;

    $statusVariant = fn (TicketStatus $status) => match ($status) {
        TicketStatus::Open => 'warn',
        TicketStatus::Answered => 'sale',
        TicketStatus::Closed => 'neutral',
    };
@endphp

<div class="space-y-4">

    <x-ui.section-heading :title="__('Support tickets')" as="h1" />

    @if ($thread)
        {{-- ===== Thread view ===== --}}
        <x-ui.button variant="plain" type="button" wire:click="backToList"
                class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-medium text-ink-soft transition-colors hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            {{ __('Back to queue') }}
        </x-ui.button>

        <x-ui.card class="p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-display text-lg font-medium">{{ $thread->subject }}</h2>
                    <p class="mt-1 text-[13px] text-ink-soft">
                        <span class="font-mono">#{{ $thread->id }}</span>
                        · {{ $thread->user?->name ?? __('Deleted user') }}@if ($thread->user) ({{ $thread->user->email }})@endif
                        · {{ __('updated :time', ['time' => $thread->updated_at->diffForHumans()]) }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($thread->priority === TicketPriority::Urgent)
                        <x-ui.badge variant="danger">{{ __('Urgent') }}</x-ui.badge>
                    @endif
                    <x-ui.badge :variant="$statusVariant($thread->status)">{{ $thread->status->label() }}</x-ui.badge>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                @foreach ($thread->replies as $ticketReply)
                    <div wire:key="reply-{{ $ticketReply->id }}"
                         class="rounded-[var(--radius-card)] border border-line p-3 {{ $ticketReply->isFromSupport() ? 'bg-surface' : 'bg-paper' }}">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-[13px] font-medium {{ $ticketReply->isFromSupport() ? 'text-emerald' : 'text-ink' }}">
                                {{ $ticketReply->isFromSupport() ? __('Support') : __('Buyer') }}
                            </p>
                            <p class="text-xs text-ink-faint">{{ $ticketReply->created_at?->diffForHumans() }}</p>
                        </div>
                        <p class="mt-1.5 whitespace-pre-line text-[13px] leading-relaxed text-ink">{{ $ticketReply->body }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 border-t border-line pt-4">
                @unless ($thread->isClosed())
                    <form wire:submit="reply" class="space-y-3">
                        <div>
                            <label for="admin-reply" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Reply') }}</label>
                            <textarea id="admin-reply" wire:model="replyBody" rows="4"
                                      placeholder="{{ __('Write a reply — the buyer gets an email and an in-app notification.') }}"
                                      class="block w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('replyBody') ? 'border-danger' : 'border-line-strong' }}"></textarea>
                            @error('replyBody')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button type="submit" wire:loading.attr="disabled" target="reply">{{ __('Send reply') }}</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="togglePriority({{ $thread->id }})">
                                {{ $thread->priority === TicketPriority::Urgent ? __('Mark normal') : __('Mark urgent') }}
                            </x-ui.button>
                            <x-ui.button variant="danger" wire:click="close({{ $thread->id }})"
                                         wire:confirm="{{ __('Close this ticket? The buyer can no longer reply.') }}">
                                {{ __('Close ticket') }}
                            </x-ui.button>
                        </div>
                    </form>
                @else
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-ink-soft">{{ __('This ticket is closed.') }}</p>
                        <x-ui.button variant="secondary" wire:click="reopen({{ $thread->id }})">{{ __('Reopen ticket') }}</x-ui.button>
                    </div>
                @endunless
            </div>
        </x-ui.card>

    @else
        {{-- ===== Queue tabs ===== --}}
        <nav class="flex gap-1 overflow-x-auto border-b border-line" aria-label="{{ __('Ticket status') }}">
            @foreach (TicketStatus::cases() as $status)
                <x-ui.tab wire:click="setTab('{{ $status->value }}')" wire:target="setTab('{{ $status->value }}')" wire:key="tab-{{ $status->value }}" :active="$tab === $status->value" :count="$counts[$status->value]">{{ $status->label() }}</x-ui.tab>
            @endforeach
        </nav>

        <x-ui.table.search :placeholder="__('Search subject or buyer')" />

        <div>
            <label for="ticket-category-filter" class="sr-only">{{ __('Category') }}</label>
            <select id="ticket-category-filter" wire:model.live="category"
                    class="block min-h-11 w-full max-w-xs rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                <option value="">{{ __('All categories') }}</option>
                @foreach (\App\Enums\TicketCategory::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>

        <x-ui.table.per-page />

        <x-ui.card class="overflow-x-auto">
            <x-ui.table-skeleton wire:loading wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage" />
            <div wire:loading.remove wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage">
            @if ($tickets->isEmpty() && $search !== '')
                <x-ui.empty-state :title="__('No tickets match')" :message="__('Try searching for something else.')" />
            @elseif ($tickets->isEmpty())
                <x-ui.empty-state :title="__('Queue clear')" :message="__('No :status tickets right now.', ['status' => (TicketStatus::tryFrom($tab) ?? TicketStatus::Open)->label()])" />
            @else
                <table class="w-full min-w-[720px] text-[13px]">
                    <thead class="sticky top-0 z-10 bg-surface">
                        <tr class="border-b border-line text-left text-ink-soft">
                            <x-ui.table.sortable field="id" :sort="$sort" :direction="$direction">#</x-ui.table.sortable>
                            <x-ui.table.sortable field="subject" :sort="$sort" :direction="$direction">{{ __('Subject') }}</x-ui.table.sortable>
                            <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Buyer') }}</th>
                            <x-ui.table.sortable field="priority" :sort="$sort" :direction="$direction">{{ __('Priority') }}</x-ui.table.sortable>
                            <x-ui.table.sortable field="messages" :sort="$sort" :direction="$direction" class="px-3 py-6.5 text-right font-medium">{{ __('Messages') }}</x-ui.table.sortable>
                            <x-ui.table.sortable field="updated" default="desc" :sort="$sort" :direction="$direction">{{ __('Updated') }}</x-ui.table.sortable>
                            <th scope="col" class="px-3 py-6.5 text-right font-medium">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="ticket-{{ $ticket->id }}">
                                <td class="px-3 py-2 font-mono text-[12px]">{{ $ticket->id }}</td>
                                <td class="px-3 py-2">
                                    <x-ui.button variant="plain" type="button" wire:click="select({{ $ticket->id }})"
                                            class="inline-flex min-h-11 items-center text-left font-medium text-ink hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        {{ $ticket->subject }}
                                    </x-ui.button>
                                    @if ($ticket->category !== 'general')
                                        <x-ui.badge variant="neutral">{{ \App\Enums\TicketCategory::from($ticket->category)->label() }}</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-ink-soft">{{ $ticket->user?->name ?? __('Deleted user') }}</td>
                                <td class="px-3 py-2">
                                    @if ($ticket->priority === TicketPriority::Urgent)
                                        <x-ui.badge variant="danger">{{ __('Urgent') }}</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="neutral">{{ __('Normal') }}</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $ticket->replies_count }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $ticket->updated_at->diffForHumans() }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-ui.button variant="plain" type="button" wire:click="togglePriority({{ $ticket->id }})"
                                                class="inline-flex min-h-11 items-center whitespace-nowrap rounded-[var(--radius-control)] border border-line-strong px-3 text-[13px] font-medium text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ $ticket->priority === TicketPriority::Urgent ? __('Mark normal') : __('Mark urgent') }}
                                        </x-ui.button>
                                        @if ($ticket->isClosed())
                                            <x-ui.button variant="plain" type="button" wire:click="reopen({{ $ticket->id }})"
                                                    class="inline-flex min-h-11 items-center whitespace-nowrap rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-medium text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Reopen') }}</x-ui.button>
                                        @else
                                            <x-ui.button variant="plain" type="button" wire:click="close({{ $ticket->id }})"
                                                    wire:confirm="{{ __('Close this ticket? The buyer can no longer reply.') }}"
                                                    class="inline-flex min-h-11 items-center whitespace-nowrap rounded-[var(--radius-control)] border border-danger px-3 text-[13px] font-medium text-danger hover:bg-danger-tint focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Close') }}</x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            </div>
        </x-ui.card>

        <x-ui.table.footer :rows="$tickets" />
    @endif
</div>
