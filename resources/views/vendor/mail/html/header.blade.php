@props(['url'])
{{-- Souk-night header band: the house glyph over the landing page's star
     field, brass rule underneath.

     Two deliberate email constraints:
     • The star field is a background IMAGE on a background-COLOR of the same
       emerald-night. Outlook desktop drops background images entirely, so the
       band has to survive as a flat colour — it does.
     • The glyph is a PNG, not the inline SVG the site uses (Gmail strips
       SVG), and the wordmark stays live TEXT so a client with images off
       still shows "HalalBizs" rather than an empty band.
     Assets: public/email/hb-{mark,stars}.png, generated from the app's own
     star-mark component and .souk-stars rule. --}}
<tr>
<td class="header" style="background-color: #03392B; background-image: url('{{ rtrim(config('app.url'), '/') }}/email/hb-stars.png'); background-repeat: repeat; border-bottom: 3px solid #A8772E; padding: 28px 0 24px; text-align: center;">
<a href="{{ $url }}" style="display: inline-block; color: #FAF7F0; font-size: 21px; font-weight: 700; letter-spacing: 0.02em; text-decoration: none;">
<img src="{{ rtrim(config('app.url'), '/') }}/email/hb-mark.png"
     alt=""
     width="56" height="56"
     style="display: block; margin: 0 auto 10px; width: 56px; height: 56px; border: 0;">
{!! $slot !!}
</a>
</td>
</tr>
