<x-mail::layout>
{{-- Header — HalalBizs wordmark on the ink frame --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
HalalBizs
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} HalalBizs · {{ __('Kuala Lumpur, Malaysia') }}<br>
{{ __('You\'re receiving this because you have a HalalBizs account.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
