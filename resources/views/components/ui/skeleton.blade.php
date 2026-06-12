@props(['class' => ''])

<div {{ $attributes->merge(['class' => "animate-pulse rounded-lg bg-line $class"]) }}></div>
