{{-- Per-document review controls (docs/08 §B). Expects $documents; the host
     component supplies verifyDocument / rejectDocument / docNotes via the
     ReviewsDocuments concern. --}}
<div class="space-y-2">
    @forelse ($documents as $document)
        @php
            $docVariant = match ($document->status) {
                \App\Enums\DocumentStatus::Pending => 'warn',
                \App\Enums\DocumentStatus::Verified => 'sale',
                \App\Enums\DocumentStatus::Rejected => 'danger',
            };
            $fileUrl = $document->getFirstMediaUrl('file');
        @endphp

        <div class="rounded-lg border border-line bg-surface p-3" wire:key="document-{{ $document->id }}">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-[13px] font-semibold text-ink">{{ $document->typeLabel() }}</p>
                <x-ui.badge :variant="$docVariant">{{ $document->status->label() }}</x-ui.badge>

                @if ($fileUrl !== '')
                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener"
                       class="ml-auto inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-[13px] font-semibold text-emerald underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        {{ __('Open file') }}
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                    </a>
                @else
                    <span class="ml-auto inline-flex min-h-11 items-center text-[13px] text-ink-faint">{{ __('No file uploaded') }}</span>
                @endif
            </div>

            @if ($document->notes)
                <p class="mt-1.5 text-[13px] text-ink-soft">{{ __('Notes: :notes', ['notes' => $document->notes]) }}</p>
            @endif

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <input type="text"
                       wire:model="docNotes.{{ $document->id }}"
                       placeholder="{{ __('Notes (optional, saved with the decision)') }}"
                       aria-label="{{ __('Notes for :type', ['type' => $document->typeLabel()]) }}"
                       class="min-h-11 w-full flex-1 rounded-lg border border-line-strong bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald sm:w-auto">
                <button type="button"
                        wire:click="verifyDocument({{ $document->id }})"
                        wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center rounded-lg border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Verify') }}
                </button>
                <button type="button"
                        wire:click="rejectDocument({{ $document->id }})"
                        wire:confirm="{{ __('Reject this document? The applicant will need to provide it again.') }}"
                        wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center rounded-lg border border-danger px-3 text-[13px] font-semibold text-danger hover:bg-danger-tint focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Reject') }}
                </button>
            </div>
        </div>
    @empty
        <p class="text-[13px] text-ink-soft">{{ __('No documents uploaded.') }}</p>
    @endforelse
</div>
