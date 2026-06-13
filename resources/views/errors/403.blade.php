@extends('errors.branded')

@section('code', '403')
@section('title', __('You can\'t view this page.'))
@section('message', __('It belongs to a different account — log in with the right one, or head back home.'))

@section('actions')
    <a href="{{ url('/') }}"
       class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald px-5 text-sm font-semibold text-white transition-colors duration-150 hover:bg-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
        {{ __('Back to home') }}
    </a>
@endsection
