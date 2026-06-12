{{-- Bell lives in the ink topbar of all three contexts — paper-on-ink styling. --}}
<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" wire:poll.60s>
    <button type="button"
            x-on:click="open = !open"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            class="relative flex size-10 items-center justify-center rounded-lg text-paper transition-colors duration-150 hover:bg-paper/10 focus-visible:ring-2 focus-visible:ring-emerald"
            aria-label="{{ $unreadCount > 0 ? __('Notifications — :count unread', ['count' => $unreadCount]) : __('Notifications') }}"
            data-testid="notification-bell">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute right-1.5 top-1.5 size-2.5 rounded-full bg-emerald ring-2 ring-ink"
                  data-testid="bell-unread-dot" aria-hidden="true"></span>
        @endif
    </button>

    <div x-show="open" x-cloak
         x-on:click.outside="open = false"
         x-transition.origin.top.right.duration.150ms
         class="absolute right-0 top-12 z-50 w-80 max-w-[calc(100vw-2rem)] rounded-[10px] border border-line bg-surface shadow-lg">
        <div class="flex items-center justify-between border-b border-line px-4 py-2.5">
            <p class="text-sm font-semibold text-ink">{{ __('Notifications') }}</p>
            @if ($unreadCount > 0)
                <span class="rounded-full bg-emerald-tint px-2 py-0.5 text-[11px] font-semibold text-emerald tnum">{{ $unreadCount }}</span>
            @endif
        </div>

        @if ($notifications->isNotEmpty())
            <ul class="max-h-96 divide-y divide-line overflow-y-auto">
                @foreach ($notifications as $notification)
                    <li wire:key="bell-{{ $notification->id }}">
                        <button type="button"
                                wire:click="open('{{ $notification->id }}')"
                                class="flex w-full items-start gap-2.5 px-4 py-3 text-left transition-colors duration-150 hover:bg-paper">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-transparent' : 'bg-emerald' }}" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] {{ $notification->read_at ? 'text-ink-soft' : 'font-medium text-ink' }}">
                                    {{ $notification->data['message'] ?? \Illuminate\Support\Str::headline(class_basename($notification->type)) }}
                                </span>
                                <span class="mt-0.5 block text-xs text-ink-faint">{{ $notification->created_at->diffForHumans() }}</span>
                            </span>
                            @unless ($notification->read_at)
                                <span class="sr-only">{{ __('Unread') }}</span>
                            @endunless
                        </button>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="px-4 py-8 text-center text-sm text-ink-soft">{{ __('No notifications yet.') }}</p>
        @endif

        <div class="border-t border-line">
            <a href="{{ $viewAllUrl }}" wire:navigate
               class="flex min-h-11 items-center justify-center text-[13px] font-semibold text-emerald hover:text-emerald-deep">
                {{ __('View all') }}
            </a>
        </div>
    </div>
</div>
