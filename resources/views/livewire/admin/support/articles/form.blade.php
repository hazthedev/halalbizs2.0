<div class="space-y-4">
    <x-ui.section-heading as="h1" :title="$editingId !== null ? __('Edit article') : __('New article')" />
    <x-ui.card class="p-4">
        <form wire:submit="save" class="space-y-4">
            

            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label for="article-category" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Category') }}</label>
                    <select id="article-category" wire:model="category"
                            class="block min-h-11 w-48 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2.5 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        @foreach ($categories as $categoryOption)
                            <option value="{{ $categoryOption->value }}">{{ $categoryOption->label() }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                </div>
                <x-ui.input :label="__('Position')" type="number" min="0" wire:model="position"
                            :error="$errors->first('position')" class="w-28" />
            </div>

            {{-- EN / BM tabs for title + body --}}
            <div x-data="{ tab: 'en' }">
                <div class="flex gap-1 border-b border-line" role="tablist">
                    <button type="button" role="tab" x-on:click="tab = 'en'"
                            x-bind:aria-selected="tab === 'en' ? 'true' : 'false'"
                            x-bind:class="tab === 'en' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                            class="min-h-11 border-b-2 px-3 text-[13px] font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        {{ __('English') }}
                    </button>
                    <button type="button" role="tab" x-on:click="tab = 'ms'"
                            x-bind:aria-selected="tab === 'ms' ? 'true' : 'false'"
                            x-bind:class="tab === 'ms' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                            class="min-h-11 border-b-2 px-3 text-[13px] font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        {{ __('Bahasa Melayu') }}
                    </button>
                    <button type="button" role="tab" x-on:click="tab = 'vi'"
                            x-bind:aria-selected="tab === 'vi' ? 'true' : 'false'"
                            x-bind:class="tab === 'vi' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                            class="min-h-11 border-b-2 px-3 text-[13px] font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        {{ __('Tiếng Việt') }}
                    </button>
                </div>

                <div class="space-y-4 pt-3">
                    <div x-show="tab === 'en'" class="space-y-4">
                        <x-ui.input :label="__('Title (English)')" wire:model="title.en" :error="$errors->first('title.en')" />
                        <x-ui.rich-editor model="body.en" id="article-body-en" :label="__('Body (English)')" :error="$errors->first('body.en')" />
                    </div>
                    <div x-show="tab === 'ms'" x-cloak class="space-y-4">
                        <x-ui.input :label="__('Title (Bahasa Melayu)')" wire:model="title.ms" :error="$errors->first('title.ms')" :hint="__('Optional — English is shown when empty.')" />
                        <x-ui.rich-editor model="body.ms" id="article-body-ms" :label="__('Body (Bahasa Melayu)')" :error="$errors->first('body.ms')" />
                    </div>
                    <div x-show="tab === 'vi'" x-cloak class="space-y-4">
                        <x-ui.input :label="__('Title (Vietnamese)')" wire:model="title.vi" :error="$errors->first('title.vi')" :hint="__('Optional — English is shown when empty.')" />
                        <x-ui.rich-editor model="body.vi" id="article-body-vi" :label="__('Body (Vietnamese)')" :error="$errors->first('body.vi')" />
                    </div>
                </div>
            </div>

            <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                <input type="checkbox" wire:model="isActive" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Published') }}
            </label>

            <div class="flex items-center gap-2">
                <x-ui.button type="submit" wire:loading.attr="disabled">{{ $editingId !== null ? __('Save article') : __('Create article') }}</x-ui.button>
                <x-ui.button variant="ghost" :href="route('admin.support.articles')">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
