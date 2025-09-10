<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Invite - LM Academy</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8fafc;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .content {
            padding: 40px 30px;
        }

        .welcome-text {
            font-size: 18px;
            color: #374151;
            margin-bottom: 24px;
            text-align: center;
        }

        .invite-details {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 24px;
            margin: 24px 0;
            border-left: 4px solid #667eea;
        }

        .invite-details h3 {
            color: #1f2937;
            font-size: 18px;
            margin-bottom: 12px;
        }

        .invite-details p {
            color: #6b7280;
            margin-bottom: 8px;
        }

        .registration-code {
            background-color: #1f2937;
            color: #ffffff;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
            margin: 24px 0;
            font-family: 'Courier New', monospace;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            margin: 24px 0;
            transition: transform 0.2s ease;
        }

        .cta-button:hover {
            transform: translateY(-2px);
        }

        .button-container {
            text-align: center;
            margin: 32px 0;
        }

        .instructions {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
        }

        .instructions h4 {
            color: #92400e;
            margin-bottom: 12px;
            font-size: 16px;
        }

        .instructions ol {
            color: #92400e;
            padding-left: 20px;
        }

        .instructions li {
            margin-bottom: 8px;
        }

        .footer {
            background-color: #f8fafc;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .footer p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .footer a {
            color: #667eea;
            text-decoration: none;
        }

        .security-note {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
        }

        .security-note p {
            color: #991b1b;
            font-size: 14px;
            text-align: center;
        }

        @media (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }

            .header, .content, .footer {
                padding: 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .registration-code {
                font-size: 18px;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1>🎓 LM Academy</h1>
            <p>Welcome to your learning journey!</p>
        </div>

        <!-- Main Content -->
        <div class="content">
            <div class="welcome-text">
                <strong>Hello!</strong> You've been invited to join LM Academy.
            </div>

            <div class="invite-details">
                <h3>📧 Registration Details</h3>
                <p><strong>Email:</strong> {{ $email }}</p>
                <p><strong>Invited by:</strong> {{ $invitedBy }}</p>
                <p><strong>Invitation Date:</strong> {{ $invitationDate }}</p>
            </div>

            <div class="registration-code">
                <div style="font-size: 14px; margin-bottom: 8px; opacity: 0.8;">Your Registration Code:</div>
                <div>{{ $registrationCode }}</div>
            </div>

            <div class="button-container">
                <a href="{{ $registrationUrl }}" class="cta-button">
                    🚀 Complete Registration
                </a>
            </div>

            <div class="instructions">
                <h4>📋 How to Register:</h4>
                <ol>
                    <li>Click the "Complete Registration" button above</li>
                    <li>Enter your registration code: <strong>{{ $registrationCode }}</strong></li>
                    <li>Create your account with a secure password</li>
                    <li>Start your learning journey!</li>
                </ol>
            </div>

            <div class="security-note">
                <p>🔒 <strong>Security Note:</strong> This registration code is unique to you and expires on {{ $expirationDate }}. Do not share it with anyone.</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>LM Academy</strong> - Empowering learners worldwide</p>
            <p>If you didn't request this invitation, please ignore this email.</p>
            <p>Need help? Contact us at <a href="mailto:support@lmacademy.com">support@lmacademy.com</a></p>
        </div>
    </div>
</body>
</html>
