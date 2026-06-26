<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-base font-bold text-ink">{{ __('Questions & Answers') }}
            <span class="text-ink-faint tnum">({{ $totalCount }})</span>
        </h2>
    </div>

    {{-- Ask form --}}
    @auth
        <form wire:submit="ask" class="space-y-2 rounded-[var(--radius-card)] border border-line bg-surface p-4">
            <label for="ask-question" class="block text-[13px] font-semibold text-ink">{{ __('Ask the seller a question') }}</label>
            <textarea id="ask-question" wire:model="question" rows="2" maxlength="500"
                      placeholder="{{ __('e.g. Is this halal-certified? Does it ship to East Malaysia?') }}"
                      class="w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"></textarea>
            @error('question') <p class="text-[13px] text-danger">{{ $message }}</p> @enderror
            <div class="flex justify-end">
                <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="ask">
                    {{ __('Post question') }}
                </x-ui.button>
            </div>
        </form>
    @else
        <p class="rounded-[var(--radius-card)] border border-line bg-paper p-4 text-[13px] text-ink-soft">
            <a href="{{ route('login') }}" wire:navigate class="font-semibold text-emerald hover:underline">{{ __('Log in') }}</a>
            {{ __('to ask the seller a question.') }}
        </p>
    @endauth

    {{-- List --}}
    <div class="space-y-4">
        @forelse ($questions as $question)
            <div class="border-b border-line pb-4" wire:key="question-{{ $question->id }}">
                <div class="flex items-start gap-2">
                    <span class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-paper text-[11px] font-bold text-ink-soft">Q</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-ink">{{ $question->question }}</p>
                        <p class="mt-0.5 text-[12px] text-ink-faint">{{ $question->askerName() }} · {{ $question->created_at->diffForHumans() }}</p>
                    </div>
                </div>

                @if ($question->isAnswered())
                    <div class="mt-2 flex items-start gap-2 pl-7">
                        <span class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald/10 text-[11px] font-bold text-emerald">A</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-ink">{{ $question->answer }}</p>
                            <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('Seller') }} · {{ $question->answered_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @else
                    <p class="mt-1 pl-7 text-[12px] font-medium text-warn">{{ __('Awaiting seller response') }}</p>
                @endif
            </div>
        @empty
            <p class="text-[13px] text-ink-soft">{{ __('No questions yet — be the first to ask.') }}</p>
        @endforelse

        @if ($hasMore)
            <div class="flex justify-center">
                <x-ui.button wire:click="loadMore" variant="secondary" wire:loading.attr="disabled" wire:target="loadMore">
                    {{ __('Show more questions') }}
                </x-ui.button>
            </div>
        @endif
    </div>
</div>
