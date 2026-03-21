<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AGRIGUARD Email Verification</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1f2937; margin: 0; padding: 0; background-color: #f3f4f6; }
        .wrapper { max-width: 480px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        h1 { font-size: 1.25rem; margin: 0 0 16px; color: #111827; }
        .code { font-size: 2rem; font-weight: 700; letter-spacing: 0.25em; color: #059669; margin: 20px 0; }
        .expiry { font-size: 0.875rem; color: #6b7280; margin-top: 8px; }
        .footer { font-size: 0.8125rem; color: #9ca3af; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <h1>AGRIGUARD Email Verification</h1>
            <p>Your verification code is:</p>
            <p class="code">{{ $code }}</p>
            <p class="expiry">This code will expire in {{ $expiresInMinutes }} minutes.</p>
            <p class="footer">If you did not request this code, you can safely ignore this email.</p>
        </div>
    </div>
</body>
</html>
