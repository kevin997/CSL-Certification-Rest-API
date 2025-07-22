# Backup Email Attachment Implementation Guide

## Overview

This guide explains how the **Spatie Laravel Backup** package has been configured to automatically send backup files as email attachments after successful backup operations.

## What's Been Implemented

### 1. Custom Event Listener

**File**: `app/Listeners/MailBackupWithAttachment.php`

This listener captures the `BackupZipWasCreated` event and automatically sends the backup file as an email attachment.

#### Key Features:
- ✅ **Email with Attachment**: Sends backup files directly as email attachments
- ✅ **File Size Check**: Automatically detects large files (>25MB) and sends notification without attachment
- ✅ **Multiple Recipients**: Configured to send to both `kevinliboire@gmail.com` and `data.analyst@cfpcsl.com`
- ✅ **Professional HTML Email**: Beautiful, responsive email template with backup details
- ✅ **Security Warning**: Includes important security reminders about sensitive data
- ✅ **Fallback for Large Files**: Sends notification with file location for large backups

### 2. Event Registration

**File**: `app/Providers/AppServiceProvider.php`

The listener is automatically registered to listen for backup completion events:

```php
Event::listen(
    BackupZipWasCreated::class,
    MailBackupWithAttachment::class
);
```

### 3. Email Configuration

**File**: `config/backup.php`

Recipients configured:
```php
'mail' => [
    'to' => ['kevinliboire@gmail.com', 'data.analyst@cfpcsl.com'],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'data.analyst@cfpcsl.com'),
        'name' => env('MAIL_FROM_NAME', 'CSL Certification API'),
    ],
],
```

## How It Works

### 1. Backup Process Flow

```
1. Scheduled backup runs (backup:run --only-db)
2. Spatie creates backup zip file
3. BackupZipWasCreated event is fired
4. MailBackupWithAttachment listener receives event
5. Listener checks file size
6. If ≤25MB: Sends email with attachment
7. If >25MB: Sends notification with file location
```

### 2. Email Types

#### Small Backup Files (≤25MB)
- **Subject**: "CSL Certification API - Database Backup Successful"
- **Attachment**: The actual backup file (.zip)
- **Content**: Backup details, security warning, professional styling

#### Large Backup Files (>25MB)
- **Subject**: "CSL Certification API - Large Database Backup Created"  
- **Content**: File location on server, backup details
- **No Attachment**: Too large for email

### 3. Email Template Features

- 📧 **Professional HTML Design**: Clean, branded email template
- 📊 **Backup Details**: File name, size, timestamp, environment
- ⚠️ **Security Warning**: Reminds recipients about sensitive data
- 📱 **Mobile Responsive**: Works on all devices
- 🎨 **CSL Branding**: Matches company styling

## Environment Variables Required

Make sure these are set in your `.env` file:

```env
# Mail Configuration
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=noreply@csl-brands.com
MAIL_FROM_NAME="CSL Certification API"

# App Configuration
APP_NAME="CSL Certification API"
```

## Testing the Implementation

### 1. Manual Test
```bash
php artisan backup:run --only-db
```

### 2. Expected Results
- ✅ Backup file created in storage/app/Laravel/
- ✅ Email sent to both recipients
- ✅ Backup file attached (if <25MB)
- ✅ Logs show successful email delivery

### 3. Check Logs
```bash
tail -f storage/logs/laravel.log
```

Look for:
- "Backup created: [filename] ([size] MB)"
- "Backup email with attachment sent successfully"

## Scheduled Backup with Email

The system is configured to automatically:

```php
// Daily database backup at 2:00 AM with email attachment
Schedule::command('backup:run --only-db')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Weekly full backup on Monday at 1:30 AM with email attachment  
Schedule::command('backup:run')
    ->weeklyOn(1, '01:30')
    ->withoutOverlapping()
    ->runInBackground();
```

## Security Considerations

### 1. Email Security
- ⚠️ **Sensitive Data**: Backup files contain sensitive database information
- 🔒 **HTTPS Email**: Use secure SMTP connections
- 📧 **Trusted Recipients**: Only send to authorized personnel
- 🗑️ **Auto-Delete**: Recipients should delete emails after downloading

### 2. File Size Limits
- **25MB Limit**: Email providers typically limit attachment sizes
- **Large File Handling**: System automatically handles large files gracefully
- **Server Storage**: Large backups remain on server storage

### 3. Access Control
- **Environment Variables**: Email credentials stored securely
- **Multiple Recipients**: Backup notifications sent to multiple stakeholders
- **Logging**: All backup attempts logged for audit trail

## Troubleshooting

### Common Issues

#### 1. Email Not Received
**Check**:
- SMTP credentials in .env
- Spam/Junk folders
- Laravel logs for mail errors

#### 2. Attachment Too Large
**Solution**: Automatic - system sends notification without attachment

#### 3. Authentication Failed
**Fix**: Use App Passwords for Gmail, check SMTP settings

#### 4. Event Not Firing
**Verify**: 
- Listener registered in AppServiceProvider
- No errors in backup process

### Debug Commands

```bash
# Test backup
php artisan backup:run --only-db

# Check backup files
ls -la storage/app/Laravel/

# View logs
tail -f storage/logs/laravel.log

# Test mail configuration
php artisan tinker
Mail::raw('Test email', function($msg) { 
    $msg->to('kevinliboire@gmail.com')->subject('Test'); 
});
```

## Benefits

### 1. Automation
- ✅ **Zero Manual Work**: Completely automated email delivery
- ✅ **Scheduled Backups**: Daily/weekly automatic backups with email
- ✅ **Error Handling**: Graceful handling of large files and errors

### 2. Monitoring
- ✅ **Immediate Notification**: Know instantly when backups complete
- ✅ **File Details**: See backup size, timestamp, environment
- ✅ **Multiple Recipients**: Team awareness of backup status

### 3. Convenience
- ✅ **Direct Access**: Backup files delivered directly to inbox
- ✅ **No Server Access**: No need to log into server to download
- ✅ **Professional Emails**: Clean, branded communication

## Implementation Complete ✅

The backup email attachment system is now fully operational and will:

1. ✅ **Automatically send emails** after each successful backup
2. ✅ **Include backup files as attachments** (when size permits)
3. ✅ **Send to multiple recipients** as configured
4. ✅ **Handle large files gracefully** with notifications
5. ✅ **Provide detailed backup information** in professional emails
6. ✅ **Log all activities** for monitoring and debugging

---

**Last Updated**: January 2025  
**Status**: ✅ Active and Operational  
**Recipients**: kevinliboire@gmail.com, data.analyst@cfpcsl.com