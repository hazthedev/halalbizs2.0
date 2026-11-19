<div class="space-y-4">

    <x-ui.section-heading :title="__('Pages')" as="h1">
        <x-slot:actions>
            <x-ui.button icon="plus" :href="route('admin.content.pages.create')">
                {{ __('Add page') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.section-heading>

    @if (session('status'))
        <div role="status" class="rounded-[var(--radius-control)] bg-emerald-tint px-4 py-3 text-sm text-emerald-deep">{{ session('status') }}</div>
    @endif

    <x-ui.card class="overflow-x-auto">
        @if ($pages->isEmpty())
            <x-ui.empty-state :title="__('No pages yet')" :message="__('Run the seeder to create the system pages, or add one above.')" />
        @else
            <table class="w-full min-w-[640px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Slug') }}</th>
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Title') }}</th>
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Published') }}</th>
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Updated') }}</th>
                        <th scope="col" class="px-3 py-6.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        @php
                            $isSystem = in_array($page->slug, \App\Livewire\Admin\Content\Pages::SYSTEM_SLUGS, true);
                            $isLockedActive = in_array($page->slug, \App\Livewire\Admin\Content\Pages::ALWAYS_ACTIVE_SLUGS, true);
                        @endphp
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="page-{{ $page->id }}">
                            <td class="px-3 py-2">
                                <span class="font-mono text-[12px]">/{{ $page->slug }}</span>
                                @if ($isSystem)
                                    <x-ui.badge variant="neutral" class="ml-1">{{ __('System') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-medium text-ink">
                                {{ $page->getTranslation('title', 'en') }}
                                @if ($page->draft !== null)
                                    <x-ui.badge variant="warn" class="ml-1">{{ __('Draft') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <button type="button" role="switch" aria-checked="{{ $page->is_active ? 'true' : 'false' }}"
                                        wire:click="toggleActive({{ $page->id }})" @disabled($isLockedActive)
                                        aria-label="{{ __('Toggle :title', ['title' => $page->getTranslation('title', 'en')]) }}"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius-control)] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald disabled:cursor-not-allowed disabled:opacity-50">
                                    <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $page->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                        <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $page->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $page->updated_at?->diffForHumans() }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.draft-actions type="page" :id="$page->id" :draft="$page->draft" :preview-path="'/page/'.$page->slug" />
                                    <x-ui.button variant="ghost" :href="route('admin.content.pages.edit', $page)">{{ __('Edit') }}</x-ui.button>
                                    @if (! $isSystem && auth()->user()->can('cms.publish'))
                                        <button type="button" wire:click="delete({{ $page->id }})"
                                                wire:confirm="{{ __('Delete this page? This cannot be undone.') }}"
                                                class="inline-flex min-h-11 w-[4.5rem] items-center justify-center rounded-[var(--radius-control)] px-2 font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Delete') }}</button>
                                    @else
                                        {{-- Reserved Delete slot keeps Edit/History in one column. --}}
                                        <span class="inline-block w-[4.5rem]" aria-hidden="true"></span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
