# Personal Email Settings Setup Guide

## Overview

Each user can now configure their own personal email settings in their profile page. When sending emails, the system will use the user's personal SMTP settings instead of the global system settings.

## Setting Up Personal Email Settings

1. **Log in to Rey CRM**
2. **Go to Profile Page**: Click on "Profile" in the navigation menu
3. **Scroll to Email Settings Section**: You'll see a new "Email Settings" section
4. **Fill in Required Fields**:
   - **SMTP Username** (Required): Your email account username
   - **SMTP Password** (Required): Your email account password or app password
   - **From Email** (Required): Email address that will appear as sender
   - **From Name** (Required): Name that will appear as sender

5. **Optional Fields** (uses system defaults if left empty):
   - **SMTP Server**: Leave empty to use system default
   - **SMTP Port**: Leave empty to use system default
   - **Encryption**: Select encryption method or use system default

## Common Email Provider Settings

### Gmail
- **SMTP Server**: smtp.gmail.com
- **SMTP Port**: 587 (TLS) or 465 (SSL)
- **Username**: your.email@gmail.com
- **Password**: Use App Password (not your regular password)
- **Encryption**: TLS or SSL

### Outlook/Hotmail
- **SMTP Server**: smtp-mail.outlook.com
- **SMTP Port**: 587
- **Username**: your.email@outlook.com or your.email@hotmail.com
- **Password**: Your account password
- **Encryption**: TLS

### Yahoo Mail
- **SMTP Server**: smtp.mail.yahoo.com
- **SMTP Port**: 587 or 465
- **Username**: your.email@yahoo.com
- **Password**: Use App Password
- **Encryption**: TLS or SSL

## Security Notes

1. **App Passwords**: For Gmail and Yahoo, you should use App Passwords instead of your regular password
2. **Two-Factor Authentication**: If you have 2FA enabled, you must use App Passwords
3. **Password Storage**: Passwords are stored in the database (consider encryption in production)

## Fallback Behavior

- If you don't configure personal email settings, the system will use global SMTP settings
- If you partially configure settings, missing fields will fall back to system defaults
- SMTP Username, Password, From Email, and From Name are required for personal settings

## Testing Your Settings

After saving your email settings:
1. Try sending a test email from the "Send Email" page
2. Check that the email is sent from your configured "From Email" address
3. Verify that your "From Name" appears correctly

## Troubleshooting

### Common Issues:
1. **Authentication Failed**: Check username and password
2. **Connection Timeout**: Verify SMTP server and port
3. **SSL/TLS Errors**: Try different encryption settings

### Error Messages:
- "SMTP settings are not fully configured": Fill in the required fields
- "SMTP authentication credentials are required": Enter username and password
- "Failed to send email": Check all settings and try again

## Support

If you encounter issues:
1. Verify your email provider's SMTP settings
2. Check if you need to enable "Less secure app access" or use App Passwords
3. Contact your system administrator for help with global settings
