<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $eventDetails['title'] }}</title>
</head>
<body style="margin:0;background:#f4f0e8;color:#1c1917;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f0e8;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;">
                @include('mail.partials.brand-header')
                <tr>
                    <td style="overflow:hidden;border:1px solid #ded8cd;border-radius:24px;background:#fffdf8;color:#1c1917;box-shadow:0 10px 32px rgba(28,25,23,0.08);">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="padding:40px 40px 18px;">
                                    <div style="margin-bottom:14px;color:#fb923c;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">{{ $eyebrow }}</div>
                                    <h1 style="margin:0;color:#1c1917;font-size:34px;line-height:1.08;letter-spacing:-1.2px;">{{ $heading }}</h1>
                                    <p style="margin:16px 0 0;color:#57534e;font-size:16px;line-height:1.6;">Hi {{ $attendance->user->name }}, <strong style="color:#1c1917;">{{ $eventDetails['title'] }}</strong> is nearly here. The essentials, one more time:</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:18px 40px;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-radius:16px;background:#f4f0e8;">
                                        <tr><td style="padding:22px 22px 8px;color:#78716c;font-size:11px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;">When</td></tr>
                                        <tr><td style="padding:0 22px 18px;color:#1c1917;font-size:15px;font-weight:700;line-height:1.5;">{{ $eventDetails['date'] }}<br>{{ $eventDetails['time'] }} · {{ $eventDetails['timezone'] }}</td></tr>
                                        <tr><td style="border-top:1px solid #ded8cd;padding:18px 22px 8px;color:#78716c;font-size:11px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;">Where</td></tr>
                                        <tr><td style="padding:0 22px 22px;color:#1c1917;font-size:15px;font-weight:700;line-height:1.5;">{{ $eventDetails['location'] }}</td></tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:18px 40px 40px;">
                                    <a href="{{ $eventDetails['event_url'] }}" style="display:inline-block;border-radius:999px;background:#bef264;padding:14px 22px;color:#1c1917;font-size:14px;font-weight:800;text-decoration:none;">View event details</a>
                                    <a href="{{ $manageUrl }}" style="display:inline-block;margin-left:8px;padding:14px 12px;color:#1d4ed8;font-size:14px;font-weight:800;text-decoration:none;">My events</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr><td style="padding:20px 8px 6px;color:#78716c;font-size:12px;line-height:1.6;">No longer attending? <a href="{{ $cancelUrl }}" style="color:#57534e;text-decoration:underline;">Review or cancel your attendance</a>.</td></tr>
                @include('mail.partials.brand-footer')
            </table>
        </td>
    </tr>
</table>
</body>
</html>
