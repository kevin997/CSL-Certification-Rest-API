<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password Reset Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #eeeeee;
        }
        .content {
            padding: 20px 0;
            line-height: 1.6;
            color: #333333;
        }
        .code-box {
            text-align: center;
            margin: 30px 0;
        }
        .code {
            display: inline-block;
            font-size: 32px;
            font-weight: bold;
            color: #000000;
            background-color: #f8f9fa;
            padding: 15px 30px;
            border-radius: 5px;
            letter-spacing: 5px;
            border: 1px solid #dee2e6;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
            text-align: center;
            font-size: 12px;
            color: #999999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Password Reset</h2>
        </div>
        <div class="content">
            <p>Hello {{ $user->name }},</p>
            <p>We received a request to reset your password for your KURSA account. Your 4-digit verification code is:</p>
            
            <div class="code-box">
                <span class="code">{{ $otp }}</span>
            </div>
            
            <p>This code will expire in 15 minutes.</p>
            <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
            
            <p>Thanks,<br>The KURSA Team</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} KURSA. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
