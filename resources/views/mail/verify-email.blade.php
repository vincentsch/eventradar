<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify your email</title>
</head>
<body style="margin:0;background:#f4f0e8;color:#1c1917;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f0e8;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;">
                @include('mail.partials.brand-header')
                <tr>
                    <td style="overflow:hidden;border:1px solid #ded8cd;border-radius:24px;background:#fffdf8;box-shadow:0 10px 32px rgba(28,25,23,0.08);">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="padding:38px 40px 16px;">
                                    <div style="margin-bottom:14px;color:#f4510b;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">Confirm your email</div>
                                    <h1 style="margin:0;font-size:34px;line-height:1.08;letter-spacing:-1.2px;">One tap and you are in.</h1>
                                    <p style="margin:16px 0 0;color:#57534e;font-size:16px;line-height:1.6;">
                                        Hi {{ $name }}, welcome to EventRadar. Confirm this address and you can join attendee lists, get your confirmations, and receive reminders before every event you save.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:24px 40px 12px;">
                                    <a href="{{ $url }}" style="display:inline-block;border-radius:999px;background:#1c1917;padding:14px 26px;color:#ffffff;font-size:14px;font-weight:800;text-decoration:none;">Verify email address</a>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px 40px 38px;color:#78716c;font-size:12px;line-height:1.6;">
                                    This link expires in {{ $expiresInMinutes }} minutes. If the button does not work, copy this address into your browser:<br>
                                    <a href="{{ $url }}" style="color:#1d4ed8;word-break:break-all;">{{ $url }}</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 8px 6px;color:#78716c;font-size:12px;line-height:1.6;">
                        You received this because an account was created with this address. If that was not you, no action is needed.
                    </td>
                </tr>
                @include('mail.partials.brand-footer')
            </table>
        </td>
    </tr>
</table>
</body>
</html>
