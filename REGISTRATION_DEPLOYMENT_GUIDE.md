# Registration System Deployment Guide

## Quick Fix for Server Issues

### 1. **Run the Schema Fix Script First**
Before testing registration, run this URL on your server:
```
https://your-domain.com/fix_registration_schema.php
```
This will fix any database schema issues.

### 2. **Common Server Problems & Solutions**

#### **Problem: Registration keeps loading / doesn't respond**
**Solution:**
1. Check if PHPMailer files exist in `/PHPMailer-master/` directory
2. Verify SMTP settings in your hosting control panel
3. Create a `.env` file in `/config/` directory with your server settings

#### **Problem: Emails not being sent**
**Solutions:**
1. **For shared hosting**: The system automatically falls back to PHP's `mail()` function
2. **For VPS/Dedicated**: Update SMTP settings in `.env` file
3. **Quick test**: Use your hosting provider's default mail settings

#### **Problem: Database errors**
**Solution:** Run the schema fix script: `/fix_registration_schema.php`

### 3. **Environment Configuration**

Create `/config/.env` file with these settings:

```env
# Basic Configuration
APP_ENV=production
APP_NAME=QPaperGen
APP_URL=https://your-actual-domain.com

# Database (get these from your hosting provider)
DB_HOST=localhost
DB_USER=your_cpanel_db_user
DB_PASSWORD=your_cpanel_db_password
DB_NAME=your_cpanel_db_name

# Email Settings (use your hosting provider's SMTP)
SMTP_HOST=mail.your-domain.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=noreply@your-domain.com
SMTP_PASSWORD=your_email_password
SMTP_DEBUG=0
```

### 4. **For Different Hosting Providers**

#### **Shared Hosting (cPanel)**
- Database settings: Found in cPanel -> MySQL Databases
- Email settings: Use your domain's email settings
- No additional configuration needed

#### **VPS/Cloud Hosting** 
- May need to configure SMTP authentication
- Check firewall settings for SMTP ports (587, 465, 25)
- Ensure PHP mail() function is enabled

### 5. **Testing Steps**

1. **Test Database Connection**: Visit `/test_registration_components.php`
2. **Fix Database Schema**: Visit `/fix_registration_schema.php` 
3. **Test Registration**: Visit `/register.php`
4. **Check Logs**: Look in your server's error logs for specific issues

### 6. **Troubleshooting Common Issues**

#### **Issue: "Class 'PHPMailer' not found"**
**Fix:** Ensure PHPMailer files are uploaded to `/PHPMailer-master/` directory

#### **Issue: "Database connection failed"**
**Fix:** Update database credentials in `/config/.env` file

#### **Issue: "Email sending failed"**
**Fix:** System will automatically try fallback email method

#### **Issue: "Table doesn't exist"**
**Fix:** Run `/fix_registration_schema.php` to create all required tables

### 7. **Server Requirements**

- PHP 7.4+ (recommended: PHP 8.0+)
- MySQL 5.7+ or MariaDB 10.3+
- OpenSSL extension (for secure tokens)
- mysqli extension
- mail() function enabled (for email fallback)

### 8. **Security Notes**

- All SQL queries use prepared statements (SQL injection protection)
- Passwords are hashed with bcrypt
- Email tokens are cryptographically secure
- Session management includes regeneration
- Input validation on all forms

### 9. **File Permissions**

Ensure these files are readable by web server:
- `/config/env.php` (644)
- `/db_connect.php` (644)
- `/phpmailer_mailer.php` (644)

### 10. **Quick Deployment Checklist**

- [ ] Upload all files to server
- [ ] Create `.env` file with correct settings
- [ ] Run `/fix_registration_schema.php`
- [ ] Test with `/test_registration_components.php`
- [ ] Try registration with a real email address
- [ ] Check email delivery
- [ ] Test email verification link

## Support

If you still have issues after following this guide:

1. Check your server's PHP error logs
2. Enable debug mode by setting `SMTP_DEBUG=2` in `.env` file
3. Contact your hosting provider about SMTP/email settings

The system is designed to work on most shared hosting providers with automatic fallbacks for common issues.
