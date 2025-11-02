# Admin Panel Security Documentation

## Overview
This document outlines the security measures implemented in the Question Paper Generator admin panel to ensure secure access and data protection.

## Security Features

### 1. Authentication & Authorization
- **Session-based authentication** with secure session management
- **Role-based access control** (admin, superadmin)
- **Automatic session cleanup** after 30 minutes of inactivity
- **Secure logout** with complete session destruction

### 2. CSRF Protection
- **CSRF tokens** generated for all forms
- **Token verification** on all POST requests
- **Secure token generation** using `random_bytes()`
- **Form validation** to prevent cross-site request forgery

### 3. Input Validation & Sanitization
- **Input sanitization** using `htmlspecialchars()` with ENT_QUOTES
- **Integer validation** with range checking
- **Email format validation** using PHP's built-in filters
- **SQL injection prevention** using prepared statements and `real_escape_string()`

### 4. Rate Limiting
- **Action-based rate limiting** to prevent abuse
- **Configurable limits** (default: 5 attempts per 5 minutes)
- **IP-based tracking** for anonymous actions
- **Session-based tracking** for authenticated users

### 5. Audit Logging
- **Admin action logging** for all CRUD operations
- **IP address tracking** for security monitoring
- **User agent logging** for device fingerprinting
- **Timestamped logs** for forensic analysis

### 6. Secure Redirects
- **Domain validation** for redirect URLs
- **Whitelist approach** for allowed domains
- **Fallback redirects** to prevent open redirects

### 7. Database Security
- **Separate admin tables** to isolate admin data
- **Soft delete functionality** to prevent data loss
- **Foreign key constraints** for data integrity
- **Indexed queries** for performance and security

## Security Headers

The admin panel implements the following security headers:
- **Content Security Policy** (CSP) to prevent XSS attacks
- **X-Frame-Options** to prevent clickjacking
- **X-Content-Type-Options** to prevent MIME type sniffing
- **Referrer Policy** to control referrer information

## File Structure

```
admin/
├── security.php          # Security helper functions
├── header.php            # Admin-specific header with navigation
├── dashboard.php         # Main admin dashboard
├── manage_questions.php  # Question management with security
├── deleted_questions.php # Soft-deleted questions management
├── users.php            # Admin user management
├── settings.php         # System settings
└── SECURITY.md          # This documentation
```

## Usage Examples

### Requiring Admin Authentication
```php
require_once __DIR__ . '/security.php';
requireAdminAuth();
```

### CSRF Protection in Forms
```php
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    <!-- form fields -->
</form>
```

### CSRF Verification in POST Handlers
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Security token invalid. Please try again.';
    } else {
        // Process form data
    }
}
```

### Input Sanitization
```php
$cleanInput = sanitizeInput($_POST['user_input']);
$validInt = validateInt($_POST['id'], 1, 1000);
$validEmail = validateEmail($_POST['email']);
```

### Rate Limiting
```php
if (!checkRateLimit('login_attempt', 5, 300)) {
    $message = 'Too many attempts. Please try again later.';
}
```

### Logging Admin Actions
```php
logAdminAction('question_created', 'Question ID: 123, Type: MCQ');
```

## Database Tables

### admins
- Stores admin user credentials and roles
- Password hashing using PHP's `password_hash()`
- Role-based permissions (admin, superadmin)

### admin_logs
- Tracks all admin actions for audit purposes
- Includes IP address, user agent, and timestamp
- Indexed for efficient querying

### deleted_questions
- Soft delete implementation for questions
- Preserves data for recovery and audit
- Automatic cleanup options

## Best Practices

1. **Always use HTTPS** in production environments
2. **Regular security audits** of admin actions
3. **Monitor failed login attempts** for potential attacks
4. **Keep dependencies updated** to latest secure versions
5. **Implement IP whitelisting** for admin access if needed
6. **Regular backup** of admin logs and data
7. **Two-factor authentication** consideration for super admins

## Security Checklist

- [x] Session management with timeout
- [x] CSRF protection on all forms
- [x] Input validation and sanitization
- [x] SQL injection prevention
- [x] XSS protection
- [x] Rate limiting implementation
- [x] Audit logging system
- [x] Secure redirect validation
- [x] Role-based access control
- [x] Password hashing
- [x] Secure headers implementation

## Incident Response

In case of security incidents:

1. **Immediate response**: Block suspicious IP addresses
2. **Investigation**: Review admin logs for affected time period
3. **Assessment**: Determine scope and impact of breach
4. **Remediation**: Fix vulnerabilities and update security measures
5. **Notification**: Inform stakeholders if necessary
6. **Documentation**: Record incident details and lessons learned

## Contact

For security concerns or questions about this implementation, please contact the development team.

---

**Last Updated**: <?= date('Y-m-d H:i:s') ?>
**Version**: 1.0
**Security Level**: High
