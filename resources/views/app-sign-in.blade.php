{{-- App web sign-in confirm step (AppWebSignInController::show): the link
     signs nobody in until this button is pressed. --}}
@extends('errors.branded')

@section('code', '')
@section('title', __('Continue as :name?', ['name' => $user->name]))
@section('message', __('This signs this browser in to HalalBizs as :email. If you did not open this from your own app, close this page.', ['email' => $user->email]))

@section('actions')
    <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6 flex flex-col items-center gap-3">
        @csrf
        <button type="submit"
                class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-5 text-sm font-medium text-white transition-colors duration-150 hover:bg-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
            {{ __('Continue') }}
        </button>
        <a href="{{ url('/') }}" class="text-sm text-ink-soft underline">{{ __('Cancel') }}</a>
    </form>
@endsection
