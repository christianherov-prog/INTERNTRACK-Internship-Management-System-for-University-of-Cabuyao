<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request - INTERNTRACK</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 0; }
        .wrapper { max-width: 580px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #0a5c2e 0%, #1a7a3f 100%); padding: 32px 36px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
        .header p { color: #d0f0dc; margin: 6px 0 0; font-size: 13px; }
        .body { padding: 36px; }
        .body h2 { color: #0a5c2e; font-size: 18px; margin: 0 0 12px; }
        .body p { color: #444; font-size: 15px; line-height: 1.7; margin: 0 0 20px; }
        .cta { text-align: center; margin: 28px 0; }
        .cta a {
            background: linear-gradient(135deg, #0a5c2e, #1a7a3f);
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
        .badge { display: inline-block; background: #e8f7ee; color: #1a7a3f; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 18px; }
        .security-notice { background: #fcfcfc; border-left: 3px solid #1a7a3f; padding: 12px 16px; margin: 20px 0; font-size: 13px; color: #666; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>🎓 INTERNTRACK</h1>
            <p>University of Cabuyao — Internship Management System</p>
        </div>
        <div class="body">
            <span class="badge">Security Notification</span>
            <h2>Password Reset Request</h2>
            <p>Hello <strong>{{ $displayName }}</strong>,</p>
            <p>We received a request to reset the password for your INTERNTRACK account. Click the button below to set a new password. This link is valid for <strong>60 minutes</strong>.</p>

            <div class="cta">
                <a href="{{ $confirmationLink }}">Reset My Password →</a>
            </div>

            <div class="security-notice">
                If you did not make this request, you can safely ignore this email. Your password will remain unchanged.
            </div>

            <p style="font-size:13px; color:#888; margin-top: 24px; word-break: break-all;">
                If the button above does not work, copy and paste this link into your browser:<br>
                <a href="{{ $confirmationLink }}" style="color: #1a7a3f;">{{ $confirmationLink }}</a>
            </p>
        </div>
        <div class="footer">
            <p>
                © {{ date('Y') }} INTERNTRACK · University of Cabuyao<br>
                This is an automated system email. Please do not reply directly.
            </p>
        </div>
    </div>
</body>
</html>
