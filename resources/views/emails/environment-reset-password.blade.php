<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }
        .content {
            padding: 20px 0;
        }
        .button {
            display: inline-block;
            background-color: #00a5ff;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .footer {
            padding: 20px 0;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $environmentName }}</h1>
        </div>
        
        <div class="content">
            <h2>Reset Your Password</h2>
            
            <p>Hello,</p>
            
            <p>You are receiving this email because we received a password reset request for your account on <strong>{{ $environmentName }}</strong>.</p>
            
            <p>This is for your environment-specific login with email: <strong>{{ $environmentEmail }}</strong></p>
            
            <p>
                <a href="{{ $resetUrl }}" class="button">Reset Password</a>
            </p>
            
            <p>If you did not request a password reset, no further action is required.</p>
            
            <p>This password reset link will expire in 60 minutes.</p>
            
            <p>If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:</p>
            
            <p>{{ $resetUrl }}</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $environmentName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
