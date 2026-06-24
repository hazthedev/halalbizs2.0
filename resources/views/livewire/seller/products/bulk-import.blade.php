<div class="mx-auto max-w-2xl px-4 py-6">
    <x-ui.section-heading as="h1" :title="__('Bulk import')" />

    <x-ui.card class="mt-4 p-5">
        <p class="text-sm text-ink-soft">
            {{ __('Upload a CSV to create products in bulk. Every product imports as a draft so you can review before publishing.') }}
        </p>

        <button type="button" wire:click="downloadTemplate" class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-line-strong px-3 text-[13px] font-medium text-ink hover:border-emerald hover:text-emerald">
            {{ __('Download template CSV') }}
        </button>

        <form wire:submit="import" class="mt-5 space-y-3">
            <input type="file" wire:model="csv" accept=".csv,text/csv"
                   class="block w-full text-sm text-ink file:mr-3 file:rounded-lg file:border file:border-line-strong file:bg-canvas file:px-3 file:py-2 file:text-[13px] file:font-medium">
            @error('csv')<p class="text-[13px] text-danger">{{ $message }}</p>@enderror

            <div wire:loading wire:target="csv" class="text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>

            <button type="submit" wire:loading.attr="disabled" wire:target="import,csv"
                    class="inline-flex min-h-11 items-center rounded-lg bg-emerald px-4 text-sm font-semibold text-white disabled:opacity-50">
                {{ __('Import products') }}
            </button>
        </form>

        @if ($result !== null)
            <div class="mt-5 rounded-lg border border-line bg-canvas p-3 text-sm">
                <p class="font-semibold text-emerald">{{ __(':n products imported as drafts.', ['n' => $result['created']]) }}</p>
                @if (! empty($result['errors']))
                    <ul class="mt-2 list-disc space-y-0.5 pl-5 text-[13px] text-danger">
                        @foreach ($result['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <p class="mt-5 text-[12px] text-ink-faint">
            {{ __('Columns:') }} <span class="font-mono">{{ implode(', ', \App\Livewire\Seller\Products\BulkImport::COLUMNS) }}</span>
        </p>
    </x-ui.card>
</div>
