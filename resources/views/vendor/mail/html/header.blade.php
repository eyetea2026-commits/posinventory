@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
<tr>
<td style="background: #3b82f6; color: #ffffff; width: 40px; height: 40px; border-radius: 10px; font-weight: 700; font-size: 16px; text-align: center; vertical-align: middle;">CE</td>
<td style="padding-left: 12px; color: #18181b; font-size: 19px; font-weight: bold; text-align: left; vertical-align: middle;">{!! $slot !!}</td>
</tr>
</table>
</a>
</td>
</tr>
