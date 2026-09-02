<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notifTitle }}</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 0; }
        .wrapper { max-width: 580px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1a237e 0%, #283593 100%); padding: 32px 36px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
        .header p { color: #c5cae9; margin: 6px 0 0; font-size: 13px; }
        .body { padding: 36px; }
        .body h2 { color: #1a237e; font-size: 18px; margin: 0 0 12px; }
        .body p { color: #444; font-size: 15px; line-height: 1.7; margin: 0 0 20px; }
        .cta { text-align: center; margin: 28px 0; }
        .cta a {
            background: linear-gradient(135deg, #1a237e, #3949ab);
            color: #ffffff;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            display: inline-block;
        }
        .footer { background: #f8f9fc; border-top: 1px solid #e8eaf0; padding: 20px 36px; text-align: center; }
        .footer p { color: #9e9e9e; font-size: 12px; margin: 0; line-height: 1.6; }
        .badge { display: inline-block; background: #e8eaf6; color: #3949ab; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 18px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <div style="font-family: Arial Black, Arial, Helvetica, sans-serif; font-weight: 900; font-size: 22px; letter-spacing: 0.5px; line-height: 1;">
                <span style="color: #c5cae9;">INTERN</span><span style="color: #ffffff;">TRACK</span>
            </div>
            <p>Internship Monitoring &amp; Documentation System</p>
        </div>
        <div class="body">
            <span class="badge">System Notification</span>
            <h2>{{ $notifTitle }}</h2>
            <p>{{ $notifMessage }}</p>

            @if ($notifLink)
            <div class="cta">
                <a href="{{ $notifLink }}">View in InternTrack →</a>
            </div>
            @endif

            <p style="font-size:13px; color:#888; margin-top: 24px;">
                This is an automated notification from InternTrack. Please do not reply to this email.
                Log in to the system to take action or view details.
            </p>
        </div>
        <div class="footer">
            <p>
                © {{ date('Y') }} InternTrack — Internship Management System<br>
                You are receiving this because you are a registered user of the InternTrack platform.
            </p>
        </div>
    </div>
</body>
</html>
