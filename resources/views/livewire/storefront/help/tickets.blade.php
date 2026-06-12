@php
    $statusVariant = fn (\App\Enums\TicketStatus $status) => match ($status) {
        \App\Enums\TicketStatus::Open => 'warn',
        \App\Enums\TicketStatus::Answered => 'sale',
        \App\Enums\TicketStatus::Closed => 'neutral',
    };
@endphp

<div class="mx-auto w-full max-w-3xl px-4 py-12 sm:py-16">

    @if ($selected)
        {{-- ===== Thread view ===== --}}
        <button type="button" wire:click="backToList"
                class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-medium text-ink-soft transition-colors hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            {{ __('All tickets') }}
        </button>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold">{{ $selected->subject }}</h1>
                <p class="mt-1 text-[13px] text-ink-soft">
                    <span class="font-mono">#{{ $selected->id }}</span> · {{ __('updated :time', ['time' => $selected->updated_at->diffForHumans()]) }}
                </p>
            </div>
            <x-ui.badge :variant="$statusVariant($selected->status)">{{ $selected->status->label() }}</x-ui.badge>
        </div>

        {{-- Replies --}}
        <div class="mt-6 space-y-3">
            @foreach ($selected->replies as $ticketReply)
                <div wire:key="reply-{{ $ticketReply->id }}"
                     class="rounded-[10px] border p-4 {{ $ticketReply->isFromSupport() ? 'border-line bg-surface' : 'border-line bg-paper' }}">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="text-[13px] font-semibold {{ $ticketReply->isFromSupport() ? 'text-emerald' : 'text-ink' }}">
                            {{ $ticketReply->isFromSupport() ? __('Support') : __('You') }}
                        </p>
                        <p class="text-xs text-ink-faint">{{ $ticketReply->created_at?->diffForHumans() }}</p>
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink">{{ $ticketReply->body }}</p>
                </div>
            @endforeach
        </div>

        @if (! $selected->isClosed())
            {{-- Reply box --}}
            <form wire:submit="reply" class="mt-6 space-y-3">
                <div>
                    <label for="reply-body" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Reply') }}</label>
                    <textarea id="reply-body" wire:model="replyBody" rows="4"
                              placeholder="{{ __('Write your reply…') }}"
                              class="block w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('replyBody') ? 'border-danger' : 'border-line-strong' }}"></textarea>
                    @error('replyBody')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Send reply') }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="close"
                                 wire:confirm="{{ __('Close this ticket? You can still read it, but not reply.') }}">
                        {{ __('Close ticket') }}
                    </x-ui.button>
                </div>
            </form>
        @else
            <p class="mt-6 rounded-[10px] border border-line bg-surface px-4 py-3 text-sm text-ink-soft">
                {{ __('This ticket is closed. Need more help? Open a new ticket.') }}
            </p>
        @endif

    @elseif ($showForm)
        {{-- ===== New ticket form ===== --}}
        <h1 class="font-display text-3xl font-bold">{{ __('New support ticket') }}</h1>
        <p class="mt-2 text-sm text-ink-soft">{{ __('Tell us what happened — order numbers help us answer faster.') }}</p>

        <form wire:submit="create" class="mt-6 space-y-4">
            <x-ui.input :label="__('Subject')" wire:model="subject" :error="$errors->first('subject')"
                        placeholder="{{ __('e.g. Order MP2606… hasn’t arrived') }}" />
            <div>
                <label for="ticket-body" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Message') }}</label>
                <textarea id="ticket-body" wire:model="body" rows="6"
                          placeholder="{{ __('Describe the problem, with as much detail as you can.') }}"
                          class="block w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('body') ? 'border-danger' : 'border-line-strong' }}"></textarea>
                @error('body')
                    <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                @else
                    <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('At least 20 characters.') }}</p>
                @enderror
            </div>
            <div class="flex items-center gap-2">
                <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Create ticket') }}</x-ui.button>
                <x-ui.button variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>

    @else
        {{-- ===== Ticket list ===== --}}
        <div class="flex items-center justify-between gap-3">
            <h1 class="font-display text-3xl font-bold">{{ __('Support tickets') }}</h1>
            <x-ui.button wire:click="startTicket">{{ __('New ticket') }}</x-ui.button>
        </div>

        @if ($tickets->isEmpty())
            <div class="mt-8 rounded-[10px] border border-line bg-surface px-6 py-16 text-center">
                <h2 class="font-display text-xl font-semibold">{{ __('No tickets yet') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('When you need a hand, open a ticket — we usually reply within one working day.') }}</p>
            </div>
            <p class="mt-6 text-center text-sm text-ink-soft">
                {{ __('Looking for quick answers?') }}
                <a href="{{ route('help.index') }}" wire:navigate class="font-medium text-emerald underline hover:text-emerald-deep">{{ __('Browse the help centre') }}</a>
            </p>
        @else
            <ul class="mt-6 divide-y divide-line rounded-[10px] border border-line bg-surface">
                @foreach ($tickets as $ticket)
                    <li wire:key="ticket-{{ $ticket->id }}">
                        <button type="button" wire:click="select({{ $ticket->id }})"
                                class="flex min-h-11 w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors duration-150 hover:bg-paper focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-ink">{{ $ticket->subject }}</span>
                                <span class="mt-0.5 block text-xs text-ink-soft">
                                    <span class="font-mono">#{{ $ticket->id }}</span>
                                    · {{ trans_choice(':count message|:count messages', $ticket->replies_count, ['count' => $ticket->replies_count]) }}
                                    · {{ $ticket->updated_at->diffForHumans() }}
                                </span>
                            </span>
                            <x-ui.badge :variant="$statusVariant($ticket->status)">{{ $ticket->status->label() }}</x-ui.badge>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
