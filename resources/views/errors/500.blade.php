@extends('errors.branded')

@section('code', '500')
@section('title', __('That\'s on us.'))
@section('message', __('An error stopped this page from loading — your orders and cart are safe. Try again in a minute.'))

@section('actions')
    <a href="{{ url('/') }}"
       class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald px-5 text-sm font-semibold text-white transition-colors duration-150 hover:bg-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
        {{ __('Back to home') }}
    </a>
@endsection
