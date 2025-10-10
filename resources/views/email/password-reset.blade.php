<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - LM Academy</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .reset-code {
            background: #007bff;
            color: white;
            font-size: 24px;
            font-weight: bold;
            padding: 15px;
            text-align: center;
            border-radius: 5px;
            margin: 20px 0;
            letter-spacing: 3px;
        }
        .button {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>LM Academy</h1>
        <h2>Password Reset Request</h2>
    </div>

    <div class="content">
        <p>Hello {{ $userName }},</p>

        <p>We received a request to reset your password for your LM Academy account. If you didn't make this request, you can safely ignore this email.</p>

        <p><strong>Click the button below to reset your password:</strong></p>
        <a href="{{ $resetUrl }}" class="button">Reset Password</a>

        <div class="warning">
            <strong>Important:</strong>
            <ul>
                <li>This reset link will expire in {{ $expirationTime }}</li>
                <li>Do not share this link with anyone</li>
                <li>If you didn't request this reset, please contact support immediately</li>
            </ul>
        </div>

        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #007bff;">{{ $resetUrl }}</p>

    </div>

    <div class="footer">
        <p>This email was sent from LM Academy. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} LM Academy. All rights reserved.</p>
    </div>
</body>
</html>
