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

    @if ($selected)
        {{-- ===== Thread view ===== --}}
        <button type="button" wire:click="backToList"
                class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-medium text-ink-soft transition-colors hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            {{ __('Back to queue') }}
        </button>

        <x-ui.card class="p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-display text-lg font-semibold">{{ $selected->subject }}</h2>
                    <p class="mt-1 text-[13px] text-ink-soft">
                        <span class="font-mono">#{{ $selected->id }}</span>
                        · {{ $selected->user->name }} ({{ $selected->user->email }})
                        · {{ __('updated :time', ['time' => $selected->updated_at->diffForHumans()]) }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($selected->priority === TicketPriority::Urgent)
                        <x-ui.badge variant="danger">{{ __('Urgent') }}</x-ui.badge>
                    @endif
                    <x-ui.badge :variant="$statusVariant($selected->status)">{{ $selected->status->label() }}</x-ui.badge>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                @foreach ($selected->replies as $ticketReply)
                    <div wire:key="reply-{{ $ticketReply->id }}"
                         class="rounded-[var(--radius-card)] border border-line p-3 {{ $ticketReply->isFromSupport() ? 'bg-surface' : 'bg-paper' }}">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-[13px] font-semibold {{ $ticketReply->isFromSupport() ? 'text-emerald' : 'text-ink' }}">
                                {{ $ticketReply->isFromSupport() ? __('Support') : __('Buyer') }}
                            </p>
                            <p class="text-xs text-ink-faint">{{ $ticketReply->created_at?->diffForHumans() }}</p>
                        </div>
                        <p class="mt-1.5 whitespace-pre-line text-[13px] leading-relaxed text-ink">{{ $ticketReply->body }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 border-t border-line pt-4">
                @unless ($selected->isClosed())
                    <form wire:submit="reply" class="space-y-3">
                        <div>
                            <label for="admin-reply" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Reply') }}</label>
                            <textarea id="admin-reply" wire:model="replyBody" rows="4"
                                      placeholder="{{ __('Write a reply — the buyer gets an email and an in-app notification.') }}"
                                      class="block w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('replyBody') ? 'border-danger' : 'border-line-strong' }}"></textarea>
                            @error('replyBody')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Send reply') }}</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="togglePriority({{ $selected->id }})">
                                {{ $selected->priority === TicketPriority::Urgent ? __('Mark normal') : __('Mark urgent') }}
                            </x-ui.button>
                            <x-ui.button variant="danger" wire:click="close({{ $selected->id }})"
                                         wire:confirm="{{ __('Close this ticket? The buyer can no longer reply.') }}">
                                {{ __('Close ticket') }}
                            </x-ui.button>
                        </div>
                    </form>
                @else
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-ink-soft">{{ __('This ticket is closed.') }}</p>
                        <x-ui.button variant="secondary" wire:click="reopen({{ $selected->id }})">{{ __('Reopen ticket') }}</x-ui.button>
                    </div>
                @endunless
            </div>
        </x-ui.card>

    @else
        {{-- ===== Queue tabs ===== --}}
        <div class="flex gap-1 overflow-x-auto border-b border-line" role="tablist">
            @foreach (TicketStatus::cases() as $status)
                <button type="button" role="tab" wire:click="setTab('{{ $status->value }}')"
                        aria-selected="{{ $tab === $status->value ? 'true' : 'false' }}"
                        class="flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-[13px] font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $tab === $status->value ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink' }}">
                    {{ $status->label() }}
                    <span class="rounded-full bg-emerald-tint px-2 py-0.5 text-[11px] font-bold text-emerald">{{ $counts[$status->value] }}</span>
                </button>
            @endforeach
        </div>

        <x-ui.card class="overflow-x-auto">
            @if ($tickets->isEmpty())
                <x-ui.empty-state :title="__('Queue clear')" :message="__('No :status tickets right now.', ['status' => TicketStatus::from($tab)->label()])" />
            @else
                <table class="w-full min-w-[720px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-ink-soft">
                            <th scope="col" class="px-3 py-2.5 font-medium">#</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Subject') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Buyer') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Priority') }}</th>
                            <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Messages') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Updated') }}</th>
                            <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="ticket-{{ $ticket->id }}">
                                <td class="px-3 py-2 font-mono text-[12px]">{{ $ticket->id }}</td>
                                <td class="px-3 py-2">
                                    <button type="button" wire:click="select({{ $ticket->id }})"
                                            class="inline-flex min-h-11 items-center text-left font-medium text-ink hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        {{ $ticket->subject }}
                                    </button>
                                </td>
                                <td class="px-3 py-2 text-ink-soft">{{ $ticket->user->name }}</td>
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
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" wire:click="togglePriority({{ $ticket->id }})"
                                                class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ $ticket->priority === TicketPriority::Urgent ? __('Normal') : __('Urgent') }}
                                        </button>
                                        @if ($ticket->isClosed())
                                            <button type="button" wire:click="reopen({{ $ticket->id }})"
                                                    class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Reopen') }}</button>
                                        @else
                                            <button type="button" wire:click="close({{ $ticket->id }})"
                                                    wire:confirm="{{ __('Close this ticket? The buyer can no longer reply.') }}"
                                                    class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Close') }}</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>
    @endif
</div>
