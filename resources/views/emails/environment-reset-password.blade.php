<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Your Password - {{ $branding['company_name'] }}</title>
    <style>
        :root {
            --primary-color: {{ $branding['primary_color'] }};
            --secondary-color: {{ $branding['secondary_color'] }};
            --accent-color: {{ $branding['accent_color'] }};
        }
        body {
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
        .logo-container {
            max-width: 200px;
            max-height: 100px;
            margin: 0 auto 20px;
        }
        .logo {
            max-width: 100%;
            height: auto;
        }
        .content {
            padding: 20px 0;
        }
        h1, h2 {
            color: var(--primary-color);
        }
        .button {
            display: inline-block;
            background-color: var(--primary-color);
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
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #121212;
                color: #e0e0e0;
            }
            .container {
                background-color: #1e1e1e;
            }
            .header, .footer {
                border-color: #333;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if(!empty($branding['logo_path']))
            <div class="logo-container">
                <img src="{{ $branding['logo_path'] }}" alt="{{ $branding['company_name'] }}" class="logo">
            </div>
            @else
            <h1>{{ $branding['company_name'] }}</h1>
            @endif
        </div>
        
        <div class="content">
            <h2>Reset Your Password</h2>
            
            <p>Hello,</p>
            
            <p>You are receiving this email because we received a password reset request for your account on <strong>{{ $branding['company_name'] }}</strong>.</p>
            
            <p>This is for your environment-specific login with email: <strong>{{ $environmentEmail }}</strong></p>
            
            <p>
                <a href="{{ $resetUrl }}" class="button">Reset Password</a>
            </p>
            
            <p>If you did not request a password reset, no further action is required.</p>
            
            <p>This password reset link will expire in 60 minutes.</p>
            
            <p style="color: #666;">If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:</p>
            
            <p>{{ $resetUrl }}</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $branding['company_name'] }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
