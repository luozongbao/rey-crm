# Rey CRM Email Functionality

## Overview
Rey CRM now includes email functionality that allows the system to send emails via SMTP. This feature enables automated notifications, customer communications, and system alerts.

## Requirements
- PHP 7.2 or higher
- Composer
- PHPMailer library

## Installation
To enable email functionality, you must install PHPMailer using Composer:

```bash
# Navigate to the Rey CRM installation directory
cd /path/to/rey-crm

# Install PHPMailer
composer require phpmailer/phpmailer
```

## Configuration
1. Log in as an administrator
2. Navigate to "System Settings"
3. Find the "Email Settings" section
4. Configure the following:
   - SMTP Host (e.g., smtp.gmail.com)
   - SMTP Port (e.g., 587 for TLS, 465 for SSL)
   - SMTP Username (your email account)
   - SMTP Password
   - From Email (the email address that will appear in the "From" field)
   - From Name (the name that will appear in the "From" field)
   - Encryption (TLS, SSL, or None)
5. Click "Update SMTP Settings" to save your configuration

## Testing Email Configuration
1. After configuring your SMTP settings, click the "Test Email Settings" button
2. Enter a recipient email address in the modal dialog
3. Click "Send Test Email" to verify your configuration
4. A success or error message will appear

## Developer API
Rey CRM includes a `sendEmail()` function for developers to use in custom modules or extensions.

### Basic Usage
```php
$to = 'recipient@example.com';
$subject = 'Email Subject';
$body = '<h1>Hello</h1><p>This is an HTML email.</p>';

$result = sendEmail($to, $subject, $body);
if ($result['success']) {
    // Email sent successfully
} else {
    // Handle error: $result['message']
}
```

### Advanced Usage
```php
$to = [
    'recipient1@example.com' => 'Recipient Name',
    'recipient2@example.com' => 'Another Recipient'
];
$subject = 'Email Subject';
$body = '<h1>Hello</h1><p>This is an HTML email.</p>';
$altBody = 'Hello. This is a plain text email.';
$attachments = ['/path/to/file.pdf', '/path/to/image.jpg'];
$cc = ['cc@example.com' => 'CC Recipient'];
$bcc = ['bcc@example.com'];
$replyTo = ['noreply@example.com' => 'No Reply'];

$result = sendEmail($to, $subject, $body, $altBody, $attachments, $cc, $bcc, $replyTo);
```

## Troubleshooting
If you're having trouble sending emails:

1. Verify your SMTP credentials are correct
2. Check that your email provider allows SMTP access
3. For Gmail, you may need to enable "Less secure apps" or use an App Password
4. Ensure port 587 (TLS) or 465 (SSL) is not blocked by your firewall
5. Check the error log for detailed error messages

## Security Notes
- SMTP passwords are stored in the database. Ensure your database is properly secured.
- Consider using environment variables for sensitive SMTP credentials in production.
- Regularly audit email sending logs to detect unauthorized use.
