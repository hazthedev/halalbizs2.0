<x-account-shell active="notifications" :title="__('Notifications')">
    @if ($notifications->isNotEmpty())
        <div class="space-y-4">
            @if ($unreadCount > 0)
                <div class="flex justify-end">
                    <x-ui.button type="button" variant="secondary" wire:click="markAllRead">{{ __('Mark all read') }}</x-ui.button>
                </div>
            @endif

            <x-ui.card class="divide-y divide-line overflow-hidden">
                @foreach ($notifications as $notification)
                    <button
                        type="button"
                        wire:key="notification-{{ $notification->id }}"
                        wire:click="markRead('{{ $notification->id }}')"
                        class="flex w-full items-start gap-3 px-4 py-3.5 text-left transition-colors duration-150 hover:bg-paper"
                    >
                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-transparent' : 'bg-emerald' }}" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm {{ $notification->read_at ? 'text-ink-soft' : 'font-medium text-ink' }}">
                                {{ $notification->data['message'] ?? \Illuminate\Support\Str::headline(class_basename($notification->type)) }}
                            </span>
                            <span class="mt-0.5 block text-xs text-ink-faint">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                        @unless ($notification->read_at)
                            <span class="sr-only">{{ __('Unread') }}</span>
                        @endunless
                    </button>
                @endforeach
            </x-ui.card>
        </div>
    @else
        <x-ui.empty-state :title="__('No notifications')" :message="__('Order updates and announcements will appear here.')" />
    @endif
</x-account-shell>
