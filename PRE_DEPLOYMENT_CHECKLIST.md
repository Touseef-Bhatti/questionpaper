# 📋 Pre-Deployment Checklist

## **CRITICAL: Complete This Checklist Before Uploading**

### **1. 🔐 Security & Credentials**

#### **A. Create Environment Configuration File**
Create `/config/.env` file with your actual server details:

```env
# Application Settings
APP_ENV=production
APP_NAME=QPaperGen
APP_URL=https://your-actual-domain.com

# Database Configuration (Get these from cPanel/hosting provider)
DB_HOST=localhost
DB_USER=your_actual_db_username
DB_PASSWORD=your_actual_db_password
DB_NAME=your_actual_database_name

# Email Configuration (Your domain email settings)
SMTP_HOST=mail.your-domain.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=noreply@your-domain.com
SMTP_PASSWORD=your_actual_email_password
SMTP_DEBUG=0
```

#### **B. Verify Sensitive Information is Removed**
✅ Check these files contain NO hardcoded credentials:
- `db_connect.php` - Should use placeholders like 'your_db_user'
- `phpmailer_mailer.php` - Should use 'your_email@your-domain.com'

### **2. 📁 File Upload Preparation**

#### **A. Required Files to Upload**
Make sure you have these modified files ready:
- ✅ `register.php` (fixed version)
- ✅ `verify_email.php` (fixed version)
- ✅ `phpmailer_mailer.php` (fixed version)
- ✅ `db_connect.php` (secured version)
- ✅ `fix_registration_schema.php` (new file)
- ✅ `config/env.php` (existing)
- ✅ `/PHPMailer-master/` folder (ensure it exists)

#### **B. File Permissions Planning**
Plan to set these permissions after upload:
- PHP files: 644 (readable by web server)
- Config files: 644
- Directories: 755

### **3. 🏗️ Server Information Collection**

#### **A. Database Information**
Collect from your hosting provider (cPanel → MySQL Databases):
- Database host: `____________________`
- Database name: `____________________`
- Database username: `____________________`
- Database password: `____________________`

#### **B. Email Information**
Collect from your hosting provider (cPanel → Email Accounts):
- SMTP host: `____________________`
- SMTP port: `____________________` (usually 587 or 465)
- Email username: `____________________`
- Email password: `____________________`
- SSL/TLS setting: `____________________`

#### **C. Domain Information**
- Your full domain: `https://____________________`
- Admin email: `____________________`

### **4. 🧪 Local Testing (Optional but Recommended)**

#### **A. Test Modified Files Locally**
If you can test locally (XAMPP):
1. Update `.env` with test database settings
2. Test registration process
3. Verify emails are formatted correctly
4. Check database operations

#### **B. Backup Current Server**
Before uploading new files:
1. Download current registration files as backup
2. Export current database structure
3. Note current configuration

### **5. 📦 Upload Strategy**

#### **A. Upload Order**
1. **First**: Upload utility files (config, schema fix)
2. **Second**: Upload core files (db_connect.php, phpmailer_mailer.php)
3. **Third**: Upload registration files
4. **Last**: Test everything

#### **B. Files to Upload in Order**
```
1. /config/env.php
2. /config/.env (your configuration)
3. db_connect.php
4. phpmailer_mailer.php
5. fix_registration_schema.php
6. register.php
7. verify_email.php
8. resend_verification.php
9. check_email.php
```

### **6. 🚀 Post-Upload Action Plan**

#### **A. Immediate Actions After Upload**
1. Visit: `https://your-domain.com/fix_registration_schema.php`
2. Visit: `https://your-domain.com/test_registration_components.php`
3. Test registration with a real email address
4. Check server error logs

#### **B. Verification Steps**
- [ ] Database connection works
- [ ] Tables are created/updated
- [ ] Registration form loads
- [ ] Email sending works
- [ ] Verification links work

### **7. 📞 Support Information**

#### **A. Your Hosting Provider Details**
- Provider name: `____________________`
- Control panel: `____________________`
- Support contact: `____________________`

#### **B. Common Hosting Provider Settings**

**For cPanel/Shared Hosting:**
```env
SMTP_HOST=mail.your-domain.com
SMTP_PORT=587
SMTP_SECURE=tls
```

**For CloudFlare/Other:**
```env
SMTP_HOST=smtp.gmail.com  # if using Gmail
SMTP_PORT=587
SMTP_SECURE=tls
```

### **8. 🛠️ Troubleshooting Preparation**

#### **A. Enable Debug Mode (if needed)**
In your `.env` file, temporarily set:
```env
APP_ENV=development
SMTP_DEBUG=2
```

#### **B. Common Issues & Solutions**
- **Database connection fails**: Check DB credentials in `.env`
- **Email not sending**: System will automatically try fallback method
- **File not found errors**: Check file upload completeness
- **Permission errors**: Set proper file permissions (644)

### **9. ✅ Final Checklist**

Before clicking upload, verify:
- [ ] `.env` file created with your actual server details
- [ ] No hardcoded credentials in any PHP files
- [ ] All modified files ready for upload
- [ ] Database and email credentials collected
- [ ] Backup of current files taken
- [ ] Upload order planned
- [ ] Post-upload testing plan ready

### **10. 🎯 Quick Reference**

**First thing to do after upload:**
```
https://your-domain.com/fix_registration_schema.php
```

**Test registration system:**
```
https://your-domain.com/test_registration_components.php
```

**Emergency fallback:**
If something breaks, restore your backup files and contact hosting support.

---

## **✅ YOU'RE READY WHEN:**
- [ ] `.env` file created with real credentials
- [ ] All sensitive info removed from code
- [ ] Server credentials collected
- [ ] Upload plan ready
- [ ] Testing strategy prepared

**Only proceed to upload when ALL boxes are checked!**
