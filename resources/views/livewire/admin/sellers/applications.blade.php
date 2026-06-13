<div class="space-y-4">

    <x-ui.section-heading :title="__('Seller applications')" :subtitle="__('Oldest first — verify the documents, then approve or reject.')" as="h1" />

    <x-ui.card class="overflow-x-auto">
        @if ($applications->isEmpty())
            <x-ui.empty-state :title="__('Queue clear')" :message="__('New seller applications appear here the moment they are submitted.')" />
        @else
            {{-- Table per design §6 — hairline rows, 13px, sticky header --}}
            <table class="w-full min-w-[760px] text-[13px]">
                <thead class="sticky top-14 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Store') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Owner email') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('State') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Submitted') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Documents') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($applications as $application)
                        <tr wire:key="application-{{ $application->id }}"
                            class="border-b border-line hover:bg-paper {{ $reviewing === $application->id ? 'bg-paper' : '' }}">
                            <td class="px-3 py-2 font-medium text-ink">{{ $application->name }}</td>
                            <td class="px-3 py-2 text-ink-soft">{{ $application->user->email }}</td>
                            <td class="px-3 py-2 text-ink-soft">{{ $application->state }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $application->created_at->diffForHumans() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-ink-soft">{{ $application->documents_count }}</td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end">
                                    <button type="button"
                                            wire:click="review({{ $application->id }})"
                                            aria-expanded="{{ $reviewing === $application->id ? 'true' : 'false' }}"
                                            class="inline-flex min-h-11 items-center whitespace-nowrap rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        {{ $reviewing === $application->id ? __('Close') : __('Review') }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        {{-- Expanding review panel --}}
                        @if ($reviewing === $application->id && $reviewingStore?->id === $application->id)
                            <tr wire:key="review-{{ $application->id }}" class="border-b border-line">
                                <td colspan="6" class="bg-paper px-4 py-4">
                                    <div class="grid gap-4 lg:grid-cols-3">

                                        {{-- Application details --}}
                                        <x-ui.card class="p-4">
                                            <h3 class="text-sm font-semibold">{{ __('Application') }}</h3>
                                            <dl class="mt-2 space-y-1.5 text-[13px]">
                                                <div class="flex justify-between gap-3">
                                                    <dt class="text-ink-soft">{{ __('Owner') }}</dt>
                                                    <dd class="text-right font-medium">{{ $reviewingStore->user->name }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt class="text-ink-soft">{{ __('Phone') }}</dt>
                                                    <dd class="text-right">{{ $reviewingStore->user->phone ?? '—' }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt class="text-ink-soft">{{ __('State') }}</dt>
                                                    <dd class="text-right">{{ $reviewingStore->state }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt class="text-ink-soft">{{ __('SST registered') }}</dt>
                                                    <dd class="text-right">{{ $reviewingStore->sst_registered ? ($reviewingStore->sst_number ?: __('Yes')) : __('No') }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt class="text-ink-soft">{{ __('Submitted') }}</dt>
                                                    <dd class="text-right">{{ $reviewingStore->created_at->format('j M Y, g:ia') }}</dd>
                                                </div>
                                            </dl>
                                            <p class="mt-3 border-t border-line pt-3 text-[13px] leading-relaxed text-ink-soft">{{ $reviewingStore->description }}</p>
                                        </x-ui.card>

                                        {{-- Bank details --}}
                                        <x-ui.card class="p-4">
                                            <h3 class="text-sm font-semibold">{{ __('Bank details') }}</h3>
                                            <dl class="mt-2 space-y-1.5 text-[13px]">
                                                @forelse ($reviewingStore->bank_details ?? [] as $key => $value)
                                                    <div class="flex justify-between gap-3">
                                                        <dt class="text-ink-soft">{{ \Illuminate\Support\Str::headline($key) }}</dt>
                                                        <dd class="text-right font-mono">{{ $value }}</dd>
                                                    </div>
                                                @empty
                                                    <p class="text-ink-soft">{{ __('No bank details provided.') }}</p>
                                                @endforelse
                                            </dl>
                                        </x-ui.card>

                                        {{-- Documents --}}
                                        <div>
                                            <h3 class="mb-2 text-sm font-semibold">{{ __('Documents') }}</h3>
                                            @include('livewire.admin.sellers.partials.documents', ['documents' => $reviewingStore->documents])
                                        </div>
                                    </div>

                                    {{-- Decision --}}
                                    <x-ui.card class="mt-4 p-4">
                                        <h3 class="text-sm font-semibold">{{ __('Decision') }}</h3>
                                        <div class="mt-2">
                                            <label for="rejection-reason-{{ $application->id }}" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Rejection reason (required to reject — emailed to the applicant)') }}</label>
                                            <textarea id="rejection-reason-{{ $application->id }}"
                                                      wire:model="rejectionReason"
                                                      rows="2"
                                                      class="block w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('rejectionReason') ? 'border-danger' : 'border-line-strong' }}"
                                                      placeholder="{{ __('e.g. The SSM certificate is unreadable — upload a clearer copy.') }}"></textarea>
                                            @error('rejectionReason')
                                                <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <x-ui.button
                                                wire:click="approve({{ $application->id }})"
                                                wire:confirm="{{ __('Approve this store? The owner gains seller access immediately and is emailed.') }}"
                                                wire:loading.attr="disabled">
                                                {{ __('Approve store') }}
                                            </x-ui.button>
                                            <x-ui.button
                                                variant="danger"
                                                wire:click="reject({{ $application->id }})"
                                                wire:confirm="{{ __('Reject this application? The reason above is emailed to the applicant.') }}"
                                                wire:loading.attr="disabled">
                                                {{ __('Reject application') }}
                                            </x-ui.button>
                                        </div>
                                    </x-ui.card>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($applications->hasPages())
        <div>{{ $applications->links() }}</div>
    @endif
</div>
