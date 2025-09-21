# Email Configuration Setup

## PHP Mail Configuration

The system uses PHP's built-in `mail()` function to send emails. To enable email functionality:

### 1. Configure PHP Mail Settings

Edit your `php.ini` file and configure the following settings:

```ini
[mail function]
; For Windows
SMTP = smtp.gmail.com
smtp_port = 587
sendmail_from = your-email@gmail.com

; For Linux/Unix
sendmail_path = /usr/sbin/sendmail -t -i
```

### 2. Gmail Configuration (Recommended)

If using Gmail SMTP:

1. Enable 2-factor authentication on your Gmail account
2. Generate an App Password:
   - Go to Google Account settings
   - Security → 2-Step Verification → App passwords
   - Generate a password for "Mail"
3. Update `config/config.php`:
   ```php
   define('SMTP_USERNAME', 'your-email@gmail.com');
   define('SMTP_PASSWORD', 'your-app-password');
   ```

### 3. Alternative Email Services

For other email providers, update the SMTP settings in `config.php`:

```php
// For Outlook/Hotmail
define('SMTP_HOST', 'smtp-mail.outlook.com');
define('SMTP_PORT', 587);

// For Yahoo
define('SMTP_HOST', 'smtp.mail.yahoo.com');
define('SMTP_PORT', 587);
```

### 4. Testing Email Functionality

1. Go to the forgot password page: `/auth/forgot-password.php`
2. Enter a valid email address
3. Check if the OTP is sent to the email
4. If email fails, the system will display the OTP on screen for testing

### 5. Production Considerations

- Ensure your server has proper mail configuration
- Consider using dedicated email services like SendGrid, Mailgun, or AWS SES for production
- Monitor email delivery rates and bounce handling
- Implement proper error logging for email failures

### 6. Security Notes

- Never commit email credentials to version control
- Use environment variables for sensitive configuration
- Implement rate limiting for password reset requests
- Log all password reset attempts for security monitoring