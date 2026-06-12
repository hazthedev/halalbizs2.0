@php
    /** @var \App\Models\Category $category */
    $children = $byParent->get($category->id, collect());
    $hasChildren = $children->isNotEmpty();
    $blocked = $hasChildren || $category->products_count > 0;
@endphp

<div wire:key="category-{{ $category->id }}" x-data="{ open: {{ $depth < 2 ? 'true' : 'false' }} }">
    <div class="flex items-center gap-2 px-3 py-1.5 hover:bg-paper" style="padding-left: {{ ($depth - 1) * 28 + 12 }}px">

        {{-- Expand / collapse --}}
        @if ($hasChildren)
            <button type="button" x-on:click="open = !open" :aria-expanded="open"
                    class="flex size-11 shrink-0 items-center justify-center rounded-lg text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald"
                    aria-label="{{ __('Toggle :name', ['name' => $category->getTranslation('name', 'en')]) }}">
                <svg class="size-4 transition-transform duration-150" :class="open ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </button>
        @else
            <span class="size-11 shrink-0" aria-hidden="true"></span>
        @endif

        {{-- Name + meta --}}
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                <span class="truncate text-[13px] font-medium {{ $category->is_active ? 'text-ink' : 'text-ink-faint line-through' }}">{{ $category->getTranslation('name', 'en') }}</span>
                @unless ($category->is_active)
                    <x-ui.badge variant="neutral">{{ __('Inactive') }}</x-ui.badge>
                @endunless
            </div>
            <p class="text-[12px] text-ink-soft">
                {{ trans_choice('{0}No products|{1}1 product|[2,*]:count products', $category->products_count, ['count' => $category->products_count]) }}
                · {{ __('Commission') }}:
                @if ($category->commission_rate !== null)
                    <span class="font-semibold text-ink">{{ rtrim(rtrim(number_format((float) $category->commission_rate, 2, '.', ''), '0'), '.') }}%</span>
                @else
                    <span class="text-ink-faint">{{ __('inherited') }}</span>
                @endif
            </p>
        </div>

        {{-- Row actions --}}
        <div class="flex shrink-0 items-center gap-0.5">
            {{-- Reorder within parent --}}
            <button type="button" wire:click="move({{ $category->id }}, -1)" @disabled($isFirst)
                    class="flex size-11 items-center justify-center rounded-lg text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald disabled:cursor-not-allowed disabled:opacity-40"
                    aria-label="{{ __('Move :name up', ['name' => $category->getTranslation('name', 'en')]) }}">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
            </button>
            <button type="button" wire:click="move({{ $category->id }}, 1)" @disabled($isLast)
                    class="flex size-11 items-center justify-center rounded-lg text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald disabled:cursor-not-allowed disabled:opacity-40"
                    aria-label="{{ __('Move :name down', ['name' => $category->getTranslation('name', 'en')]) }}">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
            </button>

            {{-- Active toggle --}}
            <button type="button" wire:click="toggleActive({{ $category->id }})" role="switch" aria-checked="{{ $category->is_active ? 'true' : 'false' }}"
                    class="flex min-h-11 items-center px-1.5 focus-visible:ring-2 focus-visible:ring-emerald"
                    aria-label="{{ $category->is_active ? __('Deactivate :name', ['name' => $category->getTranslation('name', 'en')]) : __('Activate :name', ['name' => $category->getTranslation('name', 'en')]) }}">
                <span class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-150 {{ $category->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                    <span class="inline-block size-4 transform rounded-full bg-surface transition-transform duration-150 {{ $category->is_active ? 'translate-x-[18px]' : 'translate-x-0.5' }}"></span>
                </span>
            </button>

            <button type="button" wire:click="edit({{ $category->id }})"
                    class="inline-flex min-h-11 items-center rounded-lg px-2 text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Edit') }}
            </button>

            @if ($depth < \App\Livewire\Admin\Catalog\Categories::MAX_DEPTH)
                <button type="button" wire:click="startCreate({{ $category->id }})"
                        class="inline-flex min-h-11 items-center rounded-lg px-2 text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Add child') }}
                </button>
            @endif

            @if ($blocked)
                <button type="button" disabled
                        title="{{ $hasChildren ? __('Delete its sub-categories first.') : __('This category still has products — move products first.') }}"
                        class="inline-flex min-h-11 cursor-not-allowed items-center rounded-lg px-2 text-[13px] font-medium text-ink-faint">
                    {{ __('Delete') }}
                </button>
            @else
                <button type="button" wire:click="delete({{ $category->id }})"
                        wire:confirm="{{ __('Delete ":name"? This cannot be undone.', ['name' => $category->getTranslation('name', 'en')]) }}"
                        class="inline-flex min-h-11 items-center rounded-lg px-2 text-[13px] font-medium text-danger hover:bg-danger-tint focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Delete') }}
                </button>
            @endif
        </div>
    </div>

    @if ($hasChildren)
        <div x-show="open" x-cloak>
            @foreach ($children as $child)
                @include('livewire.admin.catalog.partials.category-node', [
                    'category' => $child,
                    'depth' => $depth + 1,
                    'byParent' => $byParent,
                    'isFirst' => $loop->first,
                    'isLast' => $loop->last,
                ])
            @endforeach
        </div>
    @endif
</div>
