# 🔒 Payment Gateway Security Setup Guide

## 🚨 Critical Security Steps

### 1. Environment Variables Setup

**NEVER commit sensitive credentials to version control!**

1. Copy the environment template:
```bash
cp config/.env.example config/.env
```

2. Update `.env` with your actual credentials:
```env
# Production SafePay Credentials
SAFEPAY_ENVIRONMENT=production
SAFEPAY_API_KEY=your_actual_safepay_api_key
SAFEPAY_V1_SECRET=your_actual_safepay_v1_secret
SAFEPAY_WEBHOOK_SECRET=your_actual_safepay_webhook_secret

# Production Database
DB_PASSWORD=your_strong_database_password

# Production URLs
APP_URL=https://yourdomain.com/questionpaper
```

### 2. Database Security

1. **Set a strong database password:**
```sql
ALTER USER 'root'@'localhost' IDENTIFIED BY 'very_strong_password_here';
```

2. **Create dedicated database user:**
```sql
CREATE USER 'qpaper_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON questionbank.* TO 'qpaper_user'@'localhost';
FLUSH PRIVILEGES;
```

3. **Update your .env file:**
```env
DB_USER=qpaper_user
DB_PASSWORD=strong_password
```

### 3. File Permissions (Linux/Unix)

```bash
# Set secure permissions
chmod 600 config/.env
chmod 644 config/*.php
chmod 755 cron/*.php
chmod 755 payment/*.php

# Restrict admin access
chmod 750 admin/
```

### 4. Web Server Configuration

#### Apache (.htaccess)
```apache
# Protect environment files
<Files ".env*">
    Order allow,deny
    Deny from all
</Files>

# Protect configuration files
<Directory "config">
    Order allow,deny
    Allow from 127.0.0.1
    Allow from ::1
</Directory>

# Secure headers
Header always set X-Frame-Options DENY
Header always set X-Content-Type-Options nosniff
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=63072000; includeSubdomains; preload"
```

#### Nginx
```nginx
# Block access to sensitive files
location ~ /\.env {
    deny all;
    return 404;
}

location /config/ {
    allow 127.0.0.1;
    deny all;
}

# Security headers
add_header X-Frame-Options DENY;
add_header X-Content-Type-Options nosniff;
add_header X-XSS-Protection "1; mode=block";
add_header Strict-Transport-Security "max-age=63072000; includeSubdomains; preload";
```

### 5. SafePay Webhook Security

1. **Configure webhook URL in SafePay dashboard:**
```
https://yourdomain.com/questionpaper/payment/webhook.php
```

2. **Verify webhook signature verification is working:**
- Check webhook logs for verification status
- Test with a small payment to ensure webhooks process correctly

### 6. SSL/TLS Certificate

**Required for production!**

1. **Obtain SSL certificate** (Let's Encrypt recommended)
2. **Configure HTTPS redirect**
3. **Update APP_URL to use https://**

### 7. Regular Security Maintenance

1. **Monitor payment logs:**
```bash
tail -f /path/to/php/error.log | grep PAYMENT_EVENT
```

2. **Run health checks:**
```bash
php cron/health_check.php
```

3. **Check for security alerts:**
```sql
SELECT * FROM payment_alerts WHERE severity = 'critical' AND is_resolved = 0;
```

## 🛡️ Security Checklist

- [ ] Environment variables configured (no hardcoded credentials)
- [ ] Strong database password set
- [ ] File permissions properly configured
- [ ] Web server security headers enabled
- [ ] SSL certificate installed and configured
- [ ] Webhook signature verification working
- [ ] Rate limiting enabled
- [ ] Error logging configured
- [ ] Backup strategy implemented
- [ ] Admin access restricted to authorized users

## 🚨 Security Monitoring

### Automated Alerts
The system automatically monitors for:
- High payment failure rates
- Stuck/expired payments
- Invalid webhook signatures
- System health issues
- Unusual revenue patterns

### Manual Checks
Regularly review:
- Payment logs for suspicious activity
- Failed authentication attempts
- Database connection security
- File integrity

## 📞 Security Incident Response

If you detect a security issue:

1. **Immediate Actions:**
   - Change all API keys and secrets
   - Review recent payment logs
   - Check for unauthorized admin access
   - Notify affected users if necessary

2. **Investigation:**
   - Check payment_logs table for suspicious activity
   - Review webhook logs for invalid signatures
   - Examine access logs for unusual patterns

3. **Recovery:**
   - Update all credentials
   - Deploy security patches
   - Run security validation
   - Monitor for 48 hours post-incident

## 🔐 Additional Security Measures

### Two-Factor Authentication (Recommended)
Consider implementing 2FA for admin access using:
- Google Authenticator
- SMS verification
- Hardware tokens

### Regular Security Audits
- Review code for security vulnerabilities
- Test payment flow security
- Validate webhook signature verification
- Check for SQL injection vulnerabilities
- Test CSRF protection

### Backup Security
- Encrypt database backups
- Store backups securely (offsite)
- Test backup restoration regularly
- Implement backup retention policy

---

⚠️ **Remember**: Security is an ongoing process, not a one-time setup. Regularly update your security measures and stay informed about new threats and best practices.
