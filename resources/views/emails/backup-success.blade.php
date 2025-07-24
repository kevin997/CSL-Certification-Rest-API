<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Backup Successful</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;">
        <h2 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">
            {{ config('app.name') }} - Database Backup Successful
        </h2>
        
        <p>Hello,</p>
        
        <p>A new database backup has been generated successfully and is attached to this email.</p>
        
        <div style="background-color: #ecf0f1; padding: 15px; border-radius: 5px; margin: 15px 0;">
            <strong>Backup Details:</strong><br>
            <strong>File Name:</strong> {{ $backupName }}<br>
            <strong>File Size:</strong> {{ $backupSize }} MB<br>
            <strong>Generated At:</strong> {{ $timestamp }}<br>
            <strong>Environment:</strong> {{ $environment }}
        </div>
        
        <p style="color: #e74c3c; font-weight: bold;">
            ⚠️ Important: This backup contains sensitive database information. 
            Please store it securely and delete it from your email after downloading.
        </p>
        
        <p>This is an automated email. Please do not reply to this message.</p>
        
        <hr style="border: none; border-top: 1px solid #bdc3c7; margin: 20px 0;">
        <p style="font-size: 12px; color: #7f8c8d; text-align: center;">
            {{ config('app.name') }} System<br>
            © {{ date('Y') }} CSL. All rights reserved.
        </p>
    </div>
</body>
</html>