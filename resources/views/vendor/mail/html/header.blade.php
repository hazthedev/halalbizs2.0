@props(['url'])
<tr>
<td class="header" style="background-color: #191B1A; padding: 25px 0; text-align: center;">
<a href="{{ $url }}" style="display: inline-block; font-size: 20px; font-weight: 700; color: #F7F7F4; text-decoration: none;">
{!! $slot !!}
</a>
</td>
</tr>
