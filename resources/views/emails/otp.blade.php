<x-mail::message>
<span style="display:inline-block;background:#3b82f6;color:#fff;padding:4px 14px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:0.03em;">VERIFICATION CODE</span>

Use the code below to continue resetting your password. This code expires in **{{ $expiryMinutes ?? 5 }} minutes**.

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;">
<tr>
<td align="center">
<span style="display:inline-block;background:#f4f4f5;border:1px solid #e4e4e7;border-radius:8px;padding:16px 32px;font-size:32px;font-weight:700;letter-spacing:0.3em;color:#18181b;">{{ $otp }}</span>
</td>
</tr>
</table>

**Date & Time:** {{ now()->format('F j, Y g:i A') }}

If you did not request this code, you can safely ignore this email — your password will not change unless this code is entered.
</x-mail::message>
