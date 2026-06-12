<div class="space-y-4">

    <div class="flex items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-bold">{{ __('Staff & roles') }}</h1>
        @unless ($showInvite)
            <x-ui.button wire:click="startInvite">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('Invite admin') }}
            </x-ui.button>
        @endunless
    </div>

    {{-- One-time temp password panel --}}
    @if ($generatedPassword !== null)
        <div class="flex flex-wrap items-center gap-3 rounded-[10px] border border-emerald bg-emerald-tint px-4 py-3">
            <div class="flex-1 text-[13px] text-ink">
                <p class="font-semibold">{{ __('Temporary password for :email', ['email' => $generatedFor]) }}</p>
                <p class="mt-0.5 font-mono text-sm">{{ $generatedPassword }}</p>
                <p class="mt-0.5 text-ink-soft">{{ __('Shown once — share it securely and ask them to change it after first login.') }}</p>
            </div>
            <button type="button" wire:click="dismissPassword"
                    class="inline-flex min-h-11 items-center rounded-lg px-3 text-[13px] font-semibold text-ink hover:bg-paper focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Dismiss') }}
            </button>
        </div>
    @endif

    {{-- Invite form --}}
    @if ($showInvite)
        <x-ui.card class="p-4">
            <form wire:submit="invite" class="space-y-4">
                <h2 class="font-display text-lg font-semibold">{{ __('Invite an admin') }}</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Name')" wire:model="inviteName" :error="$errors->first('inviteName')" />
                    <x-ui.input :label="__('Email')" type="email" wire:model="inviteEmail" :error="$errors->first('inviteEmail')" />
                </div>

                <fieldset>
                    <legend class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Permissions') }}</legend>
                    <div class="grid gap-1 sm:grid-cols-2">
                        @foreach ($allPermissions as $permission)
                            <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-2 text-[13px] font-medium text-ink hover:bg-paper">
                                <input type="checkbox" wire:model="invitePermissions" value="{{ $permission }}"
                                       class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                                <span class="font-mono text-[12px]">{{ $permission }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('Each permission unlocks one admin section. A temporary password is generated for you to share.') }}</p>
                    @error('invitePermissions.*')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                </fieldset>

                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Invite admin') }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="cancelInvite">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    {{-- Admin list --}}
    <x-ui.card>
        <ul class="divide-y divide-line">
            @foreach ($admins as $admin)
                <li wire:key="admin-{{ $admin->id }}">
                    <div class="flex flex-wrap items-center gap-3 px-4 py-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] font-semibold text-ink">
                                {{ $admin->name }}
                                @if ($admin->id === auth()->id())
                                    <x-ui.badge variant="neutral" class="ml-1">{{ __('You') }}</x-ui.badge>
                                @endif
                            </p>
                            <p class="truncate text-[12px] text-ink-soft">{{ $admin->email }}</p>
                        </div>

                        <div class="hidden flex-wrap gap-1 md:flex">
                            @forelse ($admin->getDirectPermissions() as $permission)
                                <x-ui.badge variant="neutral"><span class="font-mono normal-case tracking-normal">{{ $permission->name }}</span></x-ui.badge>
                            @empty
                                <span class="text-[12px] text-ink-faint">{{ __('No direct permissions') }}</span>
                            @endforelse
                        </div>

                        <button type="button" wire:click="{{ $editingId === $admin->id ? 'cancelEdit' : 'editPermissions('.$admin->id.')' }}"
                                class="inline-flex min-h-11 items-center rounded-lg px-2 text-[13px] font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ $editingId === $admin->id ? __('Close') : __('Edit permissions') }}
                        </button>

                        @if ($admin->id !== auth()->id())
                            <button type="button" wire:click="removeAdmin({{ $admin->id }})"
                                    wire:confirm="{{ __('Remove admin access for :name? They keep their account but lose the admin panel.', ['name' => $admin->name]) }}"
                                    class="inline-flex min-h-11 items-center rounded-lg px-2 text-[13px] font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                {{ __('Remove admin') }}
                            </button>
                        @endif
                    </div>

                    {{-- Inline permission editor --}}
                    @if ($editingId === $admin->id)
                        <form wire:submit="savePermissions" class="space-y-3 border-t border-line bg-paper px-4 py-4">
                            <div class="grid gap-1 sm:grid-cols-2">
                                @foreach ($allPermissions as $permission)
                                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-2 text-[13px] font-medium text-ink hover:bg-surface">
                                        <input type="checkbox" wire:model="editPermissions" value="{{ $permission }}"
                                               class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                                        <span class="font-mono text-[12px]">{{ $permission }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex items-center gap-2">
                                <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Save permissions') }}</x-ui.button>
                                <x-ui.button variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</x-ui.button>
                            </div>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-ui.card>
</div>
