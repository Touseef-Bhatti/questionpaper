# 🚀 Production Deployment Guide

## 📋 Pre-Deployment Checklist

### 1. Environment Setup
- [ ] `.env` file configured with production credentials
- [ ] Database password set and secure
- [ ] SSL certificate installed
- [ ] Domain name configured
- [ ] SafePay production credentials obtained

### 2. Security Configuration
- [ ] All sensitive data moved to environment variables
- [ ] File permissions set correctly
- [ ] Web server security headers configured
- [ ] Database user with minimal privileges created
- [ ] Admin access properly secured

### 3. SafePay Configuration
- [ ] Production API keys configured
- [ ] Webhook URL updated in SafePay dashboard
- [ ] Test payment processed successfully
- [ ] Webhook signature verification working

## 🎯 Deployment Steps

### Step 1: Prepare Environment

1. **Clone/Upload your code:**
```bash
git clone <your-repo> /var/www/html/questionpaper
cd /var/www/html/questionpaper
```

2. **Set up environment variables:**
```bash
cp config/.env.example config/.env
# Edit .env with production values
```

3. **Set file permissions:**
```bash
chmod 600 config/.env
chmod -R 755 .
chmod -R 750 admin/
```

### Step 2: Database Setup

1. **Create production database:**
```sql
CREATE DATABASE questionbank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. **Create dedicated user:**
```sql
CREATE USER 'qpaper_prod'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON questionbank.* TO 'qpaper_prod'@'localhost';
FLUSH PRIVILEGES;
```

3. **Run migrations:**
```bash
php deploy.php --env=production
```

### Step 3: Web Server Configuration

#### Apache Virtual Host
```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/questionpaper
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    # Security Headers
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=63072000"
    
    # Block sensitive files
    <Files ".env*">
        Require all denied
    </Files>
    
    <Directory "/var/www/html/questionpaper/config">
        Require local
    </Directory>
</VirtualHost>

# HTTP to HTTPS redirect
<VirtualHost *:80>
    ServerName yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>
```

#### Nginx Configuration
```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /var/www/html/questionpaper;
    
    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=63072000";
    
    # Block sensitive files
    location ~ /\.env {
        deny all;
        return 404;
    }
    
    location /config/ {
        allow 127.0.0.1;
        deny all;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

# HTTP to HTTPS redirect
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}
```

### Step 4: SafePay Configuration

1. **Login to SafePay Dashboard**
2. **Update webhook URL:**
   ```
   https://yourdomain.com/questionpaper/payment/webhook.php
   ```
3. **Test webhook delivery**
4. **Switch to production environment**

### Step 5: Cron Jobs Setup

```bash
# Run the cron setup script
./setup_cron.sh

# Or manually add cron jobs
crontab -e

# Add these lines:
*/15 * * * * php /var/www/html/questionpaper/cron/cleanup_payments.php
0 2 * * * php /var/www/html/questionpaper/cron/daily_reports.php
*/5 * * * * php /var/www/html/questionpaper/cron/health_check.php
```

### Step 6: Final Validation

```bash
# Run deployment validation
php deploy.php --env=production

# Test payment flow
# - Create test user account
# - Attempt small payment
# - Verify webhook processing
# - Check subscription activation
```

## 🔧 Post-Deployment Configuration

### 1. Update SafePay Settings

In your SafePay dashboard:
- Update success/cancel URLs to production domain
- Configure webhook URL
- Set up IP whitelisting if required
- Enable production mode

### 2. Configure Monitoring

1. **Set up log monitoring:**
```bash
# Monitor payment events
tail -f /var/log/apache2/error.log | grep PAYMENT_EVENT

# Monitor webhook processing
tail -f /var/log/apache2/error.log | grep WEBHOOK
```

2. **Configure alerting:**
- Set up email notifications
- Configure Slack/Discord webhooks
- Set up SMS alerts for critical issues

### 3. Performance Optimization

1. **Enable PHP OPcache:**
```php
; php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
```

2. **Database optimization:**
```sql
-- Add additional indexes if needed based on usage patterns
ANALYZE TABLE payments;
OPTIMIZE TABLE payments;
```

## 📊 Monitoring & Maintenance

### Daily Tasks
- [ ] Check system health status
- [ ] Review payment failure alerts
- [ ] Monitor revenue vs. targets
- [ ] Check for stuck payments

### Weekly Tasks
- [ ] Review payment analytics
- [ ] Check for security alerts
- [ ] Validate backup integrity
- [ ] Review system performance

### Monthly Tasks
- [ ] Security audit
- [ ] Performance optimization
- [ ] Dependency updates
- [ ] Capacity planning review

## 🆘 Troubleshooting

### Common Issues

1. **Payments stuck in processing:**
```bash
# Run cleanup manually
php cron/cleanup_payments.php

# Check webhook logs
SELECT * FROM payment_webhooks WHERE processed = 0 ORDER BY created_at DESC;
```

2. **Webhook signature failures:**
```bash
# Verify webhook secret in SafePay dashboard matches .env
# Check webhook logs for pattern
SELECT * FROM payment_webhooks WHERE verified = 0 ORDER BY created_at DESC;
```

3. **Database connection issues:**
```bash
# Test database connection
php -r "
require 'db_connect.php';
echo 'Database connection: ' . ($conn->ping() ? 'OK' : 'FAILED') . PHP_EOL;
"
```

4. **SSL certificate issues:**
```bash
# Check certificate validity
openssl x509 -in /path/to/certificate.crt -text -noout | grep "Not After"

# Test SSL configuration
curl -I https://yourdomain.com/questionpaper/
```

## 🔄 Updates and Maintenance

### Updating the System

1. **Backup before updates:**
```bash
# Database backup
mysqldump -u root -p questionbank > backup_$(date +%Y%m%d).sql

# File backup
tar -czf files_backup_$(date +%Y%m%d).tar.gz /var/www/html/questionpaper
```

2. **Deploy updates:**
```bash
git pull origin main
php deploy.php --env=production
```

3. **Verify functionality:**
```bash
# Run health check
php admin/payment_health.php

# Test critical paths
curl -X POST https://yourdomain.com/questionpaper/payment/webhook.php
```

### Emergency Procedures

If the payment system goes down:

1. **Immediate response:**
   - Check system health dashboard
   - Review recent error logs
   - Verify database connectivity
   - Check SafePay service status

2. **Recovery steps:**
   - Restart web server if needed
   - Clear any stuck processes
   - Run database repair if needed
   - Restore from backup if necessary

3. **Communication:**
   - Notify users about payment issues
   - Update status page
   - Coordinate with SafePay support if needed

---

📞 **Support**: For deployment issues, check the logs and health monitoring dashboard first. Contact SafePay support for gateway-specific issues.
