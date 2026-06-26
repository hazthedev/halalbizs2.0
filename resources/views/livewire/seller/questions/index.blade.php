<div class="space-y-5">
    <div>
        <h1 class="text-xl font-bold text-ink">{{ __('Questions') }}</h1>
        <p class="text-[13px] text-ink-soft">{{ __('Answer buyer questions on your products. Unanswered questions are shown first.') }}</p>
    </div>

    <div class="space-y-3">
        @forelse ($questions as $question)
            <x-ui.card class="p-4" wire:key="seller-question-{{ $question->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <a href="{{ route('product.show', $question->product->slug) }}" target="_blank" rel="noopener"
                           class="text-[13px] font-semibold text-emerald hover:underline">{{ $question->product->getTranslation('name', app()->getLocale()) }}</a>
                        <p class="mt-1 text-sm text-ink">{{ $question->question }}</p>
                        <p class="mt-0.5 text-[12px] text-ink-faint">{{ $question->askerName() }} · {{ $question->created_at->diffForHumans() }}</p>
                    </div>
                    @if (! $question->isAnswered())
                        <x-ui.badge variant="warn">{{ __('Unanswered') }}</x-ui.badge>
                    @else
                        <x-ui.badge variant="sale">{{ __('Answered') }}</x-ui.badge>
                    @endif
                </div>

                @if ($question->isAnswered() && $answeringId !== $question->id)
                    <div class="mt-2 rounded-[var(--radius-control)] border border-line bg-paper p-3">
                        <p class="text-sm text-ink">{{ $question->answer }}</p>
                        <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('Your answer') }} · {{ $question->answered_at->diffForHumans() }}</p>
                    </div>
                @endif

                @if ($answeringId === $question->id)
                    <div class="mt-3 space-y-2">
                        <textarea wire:model="answerText" rows="3" maxlength="1000"
                                  class="w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"></textarea>
                        @error('answerText') <p class="text-[13px] text-danger">{{ $message }}</p> @enderror
                        <div class="flex gap-2">
                            <x-ui.button wire:click="saveAnswer" variant="primary" wire:loading.attr="disabled" wire:target="saveAnswer">{{ __('Post answer') }}</x-ui.button>
                            <x-ui.button wire:click="cancelAnswer" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                        </div>
                    </div>
                @else
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="startAnswer({{ $question->id }})"
                                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ $question->isAnswered() ? __('Edit answer') : __('Answer') }}
                        </button>
                        <button type="button" wire:click="hide({{ $question->id }})"
                                wire:confirm="{{ __('Hide this question from the product page?') }}"
                                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-danger px-3 text-[13px] font-semibold text-danger hover:bg-danger-tint focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('Hide') }}
                        </button>
                    </div>
                @endif
            </x-ui.card>
        @empty
            <x-ui.card class="p-8 text-center">
                <p class="text-sm text-ink-soft">{{ __('No questions yet. When buyers ask about your products, they show up here.') }}</p>
            </x-ui.card>
        @endforelse

        @if ($questions->hasPages())
            <div>{{ $questions->links() }}</div>
        @endif
    </div>
</div>
