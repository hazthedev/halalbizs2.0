<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[22px] font-bold leading-tight">{{ __('Notifications') }}</h1>
        @if ($unreadCount > 0)
            <x-ui.button type="button" variant="secondary" wire:click="markAllRead">{{ __('Mark all read') }}</x-ui.button>
        @endif
    </div>

    <div class="mt-4">
        @if ($notifications->isNotEmpty())
            <x-ui.card class="divide-y divide-line overflow-hidden">
                @foreach ($notifications as $notification)
                    <button
                        type="button"
                        wire:key="notification-{{ $notification->id }}"
                        wire:click="open('{{ $notification->id }}')"
                        class="flex w-full items-start gap-3 px-4 py-3.5 text-left transition-colors duration-150 hover:bg-paper"
                    >
                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-transparent' : 'bg-emerald' }}" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm {{ $notification->read_at ? 'text-ink-soft' : 'font-medium text-ink' }}">
                                {{ $notification->data['message'] ?? \Illuminate\Support\Str::headline(class_basename($notification->type)) }}
                            </span>
                            @if (! empty($notification->data['detail']))
                                <span class="mt-0.5 block truncate text-[13px] text-ink-soft">{{ $notification->data['detail'] }}</span>
                            @endif
                            <span class="mt-0.5 block text-xs text-ink-faint">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                        @unless ($notification->read_at)
                            <span class="sr-only">{{ __('Unread') }}</span>
                        @endunless
                    </button>
                @endforeach
            </x-ui.card>
        @else
            <x-ui.card class="px-6 py-16 text-center">
                <p class="font-display text-xl font-semibold">{{ __('No notifications') }}</p>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Operational alerts — applications, payouts, disputes — appear here.') }}</p>
            </x-ui.card>
        @endif
    </div>
</div>
