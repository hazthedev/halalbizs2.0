@props(['type', 'id', 'draft' => null, 'previewPath' => '/'])

{{-- Row actions for an item with a pending draft (App\Services\ContentDrafts):
     Preview for everyone on the screen, Publish / Throw away for publishers. --}}
@if ($draft !== null)
    <a href="{{ route('admin.content.preview', ['path' => $previewPath]) }}" target="_blank" rel="noopener"
       class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Preview') }}</a>
    @can('cms.publish')
        <button type="button" wire:click="publishDraft('{{ $type }}', {{ $id }})"
                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-emerald hover:bg-emerald-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Publish') }}</button>
        <button type="button" wire:click="discardDraft('{{ $type }}', {{ $id }})"
                wire:confirm="{{ __('Throw away this draft? The live version stays as it is.') }}"
                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Throw away') }}</button>
    @endcan
@endif
