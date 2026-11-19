@extends('errors.branded')

@php $adminArea = request()->is('admin', 'admin/*'); @endphp

@section('code', '404')
@section('title', __('Page not found.'))
@section('message', $adminArea
    ? __('This record or its file was not found. A document may not have been uploaded yet.')
    : __('The link may be old, or the product may have been delisted — search for it instead.'))

@section('actions')
@if ($adminArea)
    <a href="{{ route('admin.dashboard') }}"
       class="mt-6 inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-5 text-sm font-medium text-white transition-colors duration-150 hover:bg-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
        {{ __('Back to dashboard') }}
    </a>
@else
    <form method="GET" action="{{ url('/search') }}" class="mt-6 flex gap-2">
        <label for="error-search" class="sr-only">{{ __('Search products') }}</label>
        <input id="error-search" type="search" name="q" placeholder="{{ __('Search products, stores…') }}"
               class="h-11 w-full min-w-0 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
        <button type="submit"
                class="inline-flex h-11 shrink-0 items-center rounded-[var(--radius-control)] border border-ink px-4 text-sm font-medium text-ink transition-colors duration-150 hover:bg-surface focus-visible:ring-2 focus-visible:ring-emerald">
            {{ __('Search') }}
        </button>
    </form>
    <a href="{{ url('/') }}"
       class="mt-3 inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-5 text-sm font-medium text-white transition-colors duration-150 hover:bg-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
        {{ __('Browse home') }}
    </a>
@endif
@endsection
