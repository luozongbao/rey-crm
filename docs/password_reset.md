# Rey CRM Password Reset Functionality

## Overview
Rey CRM includes a secure password reset system that allows users to reset their password via email. The system generates time-limited, single-use tokens and sends password reset links to users' registered email addresses.

## Features
- "Forgot Password" link on login page
- Email-based password reset process
- Time-limited tokens (default: 24 hours)
- Single-use tokens for security
- Secure password update form with confirmation

## Configuration
The password reset token expiration time can be configured in `includes/config.php`:

```php
// Password reset configuration
define('PASSWORD_RESET_EXPIRY_HOURS', 24); // Token validity in hours
```

## Database Schema
The system uses a `password_reset_tokens` table with the following structure:

```sql
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expiry_date DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
```

## Process Flow

1. **Request Password Reset**
   - User clicks "Forgot Password" on login page
   - User enters their email address
   - System generates a secure token and stores it in the database
   - System sends an email with a password reset link to the user

2. **Token Verification**
   - User clicks the link in their email
   - System validates the token for:
     - Existence in the database
     - Not already used (used = 0)
     - Not expired (expiry_date > current time)
   - If valid, shows the password reset form; otherwise, shows an error

3. **Password Update**
   - User enters a new password and confirmation
   - System validates the password requirements
   - System updates the user's password and marks the token as used
   - User is redirected to the login page

## Security Considerations

- Token expiration limits the window of opportunity for attacks
- Single-use tokens prevent reuse of reset links
- Generic success messages avoid email address enumeration
- Password requirements enforce strong passwords
- Database transactions ensure data consistency

## Troubleshooting

If users report issues with password resets:

1. Check that the email functionality is working properly
2. Verify that the user's email address in the database is correct
3. Check server logs for any errors during token generation or email sending
4. Ensure the token expiration time is reasonable for your users
5. Verify database connectivity and proper table creation
