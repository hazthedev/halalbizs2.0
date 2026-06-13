@props(['url'])
<tr>
<td class="header" style="background-color: #1A1714; border-bottom: 2px solid #A8772E; padding: 25px 0; text-align: center;">
<a href="{{ $url }}" style="display: inline-block; font-size: 20px; font-weight: 700; color: #F7F7F4; text-decoration: none;">
{!! $slot !!}
</a>
</td>
</tr>
