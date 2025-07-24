<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Large Database Backup Created</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;">
        <h2 style="color: #f39c12; border-bottom: 2px solid #f39c12; padding-bottom: 10px;">
            Large Database Backup Created
        </h2>
        
        <p>Hello,</p>
        
        <p>A database backup has been generated successfully, but the file is too large to attach to this email.</p>
        
        <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #f39c12;">
            <strong>Backup Details:</strong><br>
            <strong>File Name:</strong> {{ $backupName }}<br>
            <strong>File Size:</strong> {{ $backupSize }} MB<br>
            <strong>Generated At:</strong> {{ $timestamp }}<br>
            <strong>Environment:</strong> {{ $environment }}<br>
            <strong>File Location:</strong> {{ $backupPath }}
        </div>
        
        <p style="color: #856404;">
            📁 The backup file is available on the server at the specified location. 
            Please download it manually from the server.
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