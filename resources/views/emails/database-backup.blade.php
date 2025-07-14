<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Database Backup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
        }
        .header {
            background-color: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid #0066cc;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #777;
        }
        .details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .details table {
            width: 100%;
            border-collapse: collapse;
        }
        .details table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .details table td:first-child {
            font-weight: bold;
            width: 40%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>CSL Certification API - Database Backup</h2>
        </div>
        
        <p>Hello,</p>
        
        <p>A new database backup has been generated for the CSL Certification API. The backup file is attached to this email.</p>
        
        <div class="details">
            <table>
                <tr>
                    <td>Backup File:</td>
                    <td>{{ $backupFileName }}</td>
                </tr>
                <tr>
                    <td>File Size:</td>
                    <td>{{ $backupSizeMb }} MB</td>
                </tr>
                <tr>
                    <td>Generated At:</td>
                    <td>{{ $timestamp }}</td>
                </tr>
                <tr>
                    <td>Environment:</td>
                    <td>{{ $environment }}</td>
                </tr>
            </table>
        </div>
        
        <p>This is an automated email. Please do not reply to this message.</p>
        
        <div class="footer">
            <p>CSL Certification API System</p>
            <p>© {{ date('Y') }} CSL. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
