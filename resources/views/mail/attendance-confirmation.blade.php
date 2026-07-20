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
                    <td style="overflow:hidden;border:1px solid #ded8cd;border-radius:24px;background:#fffdf8;box-shadow:0 10px 32px rgba(28,25,23,0.08);">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="padding:38px 40px 16px;">
                                    <div style="margin-bottom:14px;color:#f4510b;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">Attendance confirmed</div>
                                    <h1 style="margin:0;font-size:34px;line-height:1.08;letter-spacing:-1.2px;">You are on the list.</h1>
                                    <p style="margin:16px 0 0;color:#57534e;font-size:16px;line-height:1.6;">
                                        Hi {{ $attendance->user->name }}, you are marked as <strong style="color:#1c1917;">{{ $eventDetails['intent'] }}</strong> for <strong style="color:#1c1917;">{{ $eventDetails['title'] }}</strong>. Everything you need is below.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:18px 40px 8px;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;background:#f4f0e8;border-radius:16px;">
                                        <tr><td style="padding:20px 22px 8px;color:#78716c;font-size:11px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;">When</td></tr>
                                        <tr><td style="padding:0 22px 18px;font-size:15px;font-weight:700;line-height:1.5;">{{ $eventDetails['date'] }}<br>{{ $eventDetails['time'] }} · {{ $eventDetails['timezone'] }}</td></tr>
                                        <tr><td style="border-top:1px solid #ded8cd;padding:18px 22px 8px;color:#78716c;font-size:11px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;">Where</td></tr>
                                        <tr><td style="padding:0 22px 20px;font-size:15px;font-weight:700;line-height:1.5;">{{ $eventDetails['location'] }}</td></tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:24px 40px 40px;">
                                    <a href="{{ $eventDetails['event_url'] }}" style="display:inline-block;border-radius:999px;background:#1c1917;padding:14px 22px;color:#ffffff;font-size:14px;font-weight:800;text-decoration:none;">View event</a>
                                    <a href="{{ $manageUrl }}" style="display:inline-block;margin-left:8px;padding:14px 12px;color:#1d4ed8;font-size:14px;font-weight:800;text-decoration:none;">Manage my events</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 8px 6px;color:#78716c;font-size:12px;line-height:1.6;">
                        You will receive reminders three days and 24 hours before the event. Changed your mind?
                        <a href="{{ $cancelUrl }}" style="color:#57534e;text-decoration:underline;">Review or cancel your attendance</a>.
                    </td>
                </tr>
                @include('mail.partials.brand-footer')
            </table>
        </td>
    </tr>
</table>
</body>
</html>
