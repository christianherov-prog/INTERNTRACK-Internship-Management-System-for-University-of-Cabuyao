<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Locked - INTERNTRACK</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 0; }
        .wrapper { max-width: 580px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #0a5c2e 0%, #1a7a3f 100%); padding: 24px 36px; text-align: center; color: #ffffff; }
        .body { padding: 32px 36px; }
        .body h2 { color: #b42318; font-size: 18px; margin: 0 0 12px; }
        .body p, .body li { color: #444; font-size: 15px; line-height: 1.7; }
        .notice { background: #fff7ed; border-left: 3px solid #d97706; padding: 12px 16px; margin: 20px 0; font-size: 14px; color: #5f4b1f; }
        .footer { background: #f8f9fc; border-top: 1px solid #e8eaf0; padding: 18px 36px; text-align: center; color: #9e9e9e; font-size: 12px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <div style="font-weight: 900; font-size: 22px;">INTERNTRACK</div>
            <div style="font-size: 13px; margin-top: 4px;">University of Cabuyao</div>
        </div>
        <div class="body">
            <h2>Your account has been locked</h2>
            <p>Hello {{ $displayName }},</p>
            <p>Your InternTrack account was locked on <strong>{{ $lockedAtDisplay }}</strong> after {{ $attempts }} consecutive failed sign-in attempts.</p>
            <div class="notice">Sign-in is blocked, even with the correct password, until the account is unlocked.</div>
            <p><strong>What to do next</strong></p>
            <ul>
                <li>Contact {{ $supportContact }} to verify your identity and unlock the account.</li>
                <li>If you did not try to sign in, someone may be guessing your password. Tell the administrator when you ask for the unlock.</li>
                <li>InternTrack will never ask for your password by email.</li>
            </ul>
        </div>
        <div class="footer">This is an automated security notification from InternTrack. Please do not reply.</div>
    </div>
</body>
</html>
