<div>
    <x-ui.section-heading as="h1" :title="__('Notifications')">
        @if ($unreadCount > 0)
            <x-slot:actions>
                <x-ui.button type="button" variant="secondary" wire:click="markAllRead">{{ __('Mark all read') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.section-heading>

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
            <x-ui.empty-state :title="__('No notifications')" :message="__('New orders, messages and account updates will appear here.')" />
        @endif
    </div>
</div>
