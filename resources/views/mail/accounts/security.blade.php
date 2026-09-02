<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;background:#071312;color:#f6f0df;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#071312;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#0d1d1b;border:1px solid #66552b;border-radius:18px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 32px 16px;border-bottom:1px solid #2a342f;">
                        <div style="font-size:20px;font-weight:800;letter-spacing:.08em;color:#f0cf72;">KINGSHOT ALLIANCE</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        @if (filled($eyebrow ?? null))
                            <div style="font-size:12px;font-weight:800;letter-spacing:.16em;color:#d8b85e;">{{ $eyebrow }}</div>
                        @endif
                        <h1 style="margin:10px 0 16px;font-size:30px;line-height:1.2;color:#fff9e8;">{{ $heading }}</h1>
                        <p style="margin:0 0 24px;font-size:16px;line-height:1.65;color:#c7d0cb;">{{ $intro }}</p>
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="border-radius:10px;background:#d8b85e;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 22px;color:#071312;text-decoration:none;font-weight:800;">{{ $actionText }}</a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:24px 0 8px;font-size:14px;line-height:1.6;color:#e7ddc2;">{{ $expiry }}</p>
                        <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#aebcb5;">{{ $notice }}</p>
                        <div style="border-top:1px solid #2a342f;padding-top:20px;">
                            <p style="margin:0 0 8px;font-size:12px;line-height:1.5;color:#8fa098;">{{ __('accounts.mail.fallback') }}</p>
                            <p style="margin:0;word-break:break-all;font-size:12px;line-height:1.5;color:#d8b85e;">{{ $actionUrl }}</p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 32px;background:#091816;border-top:1px solid #2a342f;font-size:11px;line-height:1.5;color:#7f9189;">
                        {{ __('accounts.mail.footer') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
