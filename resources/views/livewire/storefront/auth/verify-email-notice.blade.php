<div class="mx-auto w-full max-w-md px-4 py-12 sm:py-16">
    <x-ui.card class="p-6 sm:p-8 text-center">
        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-tint">
            <svg class="size-6 text-emerald" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
        </div>

        <h1 class="mt-4 font-display text-[28px] font-bold leading-tight">{{ __('Verify your email') }}</h1>
        <p class="mt-2 text-sm text-ink-soft">
            {{ __('We sent a verification link to') }}
            <span class="font-semibold text-ink">{{ auth()->user()->email }}</span>.
            {{ __('Click the link in that email to finish setting up your account.') }}
        </p>

        @if ($status)
            <p class="mt-4 rounded-[var(--radius-control)] bg-emerald-tint px-3.5 py-2.5 text-[13px] text-emerald">{{ $status }}</p>
        @endif

        @error('resend')
            <p class="mt-4 text-[13px] text-danger">{{ $message }}</p>
        @enderror

        <div class="mt-6 space-y-3">
            <x-ui.button type="button" wire:click="resend" class="w-full" wire:loading.attr="disabled">
                <svg wire:loading wire:target="resend" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/></svg>
                {{ __('Resend verification email') }}
            </x-ui.button>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.button type="submit" variant="ghost" class="w-full">{{ __('Log out') }}</x-ui.button>
            </form>
        </div>
    </x-ui.card>
</div>
