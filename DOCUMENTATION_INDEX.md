# AI Key Orchestration System - Documentation Index

## 📚 Complete Documentation Map

Welcome to the AI Key Orchestration & Load Balancing System. This document helps you navigate all available resources.

---

## 🎯 Start Here

### For First-Time Implementation
1. **Start:** [Quick Reference](QUICK_REFERENCE.md) - 5-minute overview
2. **Learn:** [Implementation Summary](AI_IMPLEMENTATION_SUMMARY.md) - System overview
3. **Deploy:** [Integration Checklist](INTEGRATION_CHECKLIST.md) - Step-by-step setup
4. **Reference:** [Complete Documentation](AI_KEY_ORCHESTRATION_SYSTEM.md) - Full details

### For Integration into Existing Code
1. **See Examples:** [Implementation Example](IMPLEMENTATION_EXAMPLE.php) - Real code samples
2. **Copy Pattern:** Use `AIRequestHelper::generateQuestion()` instead of direct API calls
3. **Test Locally:** Run example code in development environment
4. **Deploy:** Follow integration checklist

### For Production Operations
1. **Setup:** [Integration Checklist](INTEGRATION_CHECKLIST.md) - Complete setup guide
2. **Monitor:** Use Admin API endpoints for monitoring
3. **Troubleshoot:** See [Troubleshooting Section](AI_KEY_ORCHESTRATION_SYSTEM.md#troubleshooting)
4. **Scale:** Plan for growth using monitoring data

---

## 📖 Documentation Files

### Core Documentation

#### [QUICK_REFERENCE.md](QUICK_REFERENCE.md) ⭐ START HERE
- One-page quick reference for common tasks
- File overview and directory structure
- Key selection algorithm visualization
- Error handling matrix
- Common database queries
- Cron job configuration
- Admin API endpoints list

**Read Time:** 5 minutes  
**Best For:** Quick lookups and reminders

---

#### [AI_IMPLEMENTATION_SUMMARY.md](AI_IMPLEMENTATION_SUMMARY.md)
- High-level system overview
- What has been delivered
- Architectural decisions explained
- Key files and their purposes
- Integration steps
- Production readiness checklist
- Performance characteristics

**Read Time:** 10 minutes  
**Best For:** Understanding the system at a glance

---

#### [AI_KEY_ORCHESTRATION_SYSTEM.md](AI_KEY_ORCHESTRATION_SYSTEM.md) 📖 COMPLETE REFERENCE
- Complete system documentation (40+ pages)
- Architecture diagrams
- Detailed database schema
- Key selection algorithm with examples
- Failure handling procedures
- Rate limiting and throttling
- Daily reset and cron jobs
- Frontend usage guidelines
- Configuration details
- Admin API documentation
- Monitoring and analytics
- Security considerations
- Troubleshooting guide
- Future enhancements

**Read Time:** 30-45 minutes  
**Best For:** Comprehensive understanding and reference

---

#### [INTEGRATION_CHECKLIST.md](INTEGRATION_CHECKLIST.md)
- Phase-by-phase deployment guide
- Database setup steps
- Environment configuration
- API key management
- Cron job configuration (Linux/Mac/Windows)
- Code integration steps
- Testing procedures
- Security review
- Production deployment
- Post-deployment monitoring

**Read Time:** 20 minutes (to review)  
**Best For:** Following along during implementation

---

#### [IMPLEMENTATION_EXAMPLE.php](IMPLEMENTATION_EXAMPLE.php)
- Real-world code examples
- Function implementations
- Error handling patterns
- Batch processing example
- Monitoring helper functions
- Before/after comparisons
- Copy-paste ready code

**Read Time:** 15 minutes  
**Best For:** Learning how to integrate into existing code

---

### Code Files

#### Services (Core Implementation)

**[services/AIKeyManager.php](services/AIKeyManager.php)**
- API key encryption/decryption (AES-256)
- Key selection with account priority + least-used algorithm
- Quota and usage tracking
- Key status management
- Failure counting with circuit breaker
- ~600 lines with extensive comments

**Key Methods:**
```php
selectNextKey($service)              # Get next best key
encryptKey($plainKey)                # Encrypt for storage
storeNewKey($accountId, $key, $limit) # Add key to database
temporarilyBlockKey($keyId)          # Rate limit handling
disableKey($keyId, $reason)          # Permanent disable
```

---

**[services/AIGateway.php](services/AIGateway.php)**
- Main request orchestration engine
- Automatic failover with retry logic
- Rate limit detection (429 handling)
- Error classification and recovery
- Exponential backoff (100ms-2000ms)
- ~500 lines with detailed comments

**Key Methods:**
```php
executeRequest($service, $model, $payload, $options)  # Main entry point
```

---

**[services/AILoggingService.php](services/AILoggingService.php)**
- Request logging with cost calculation
- Usage statistics and reporting
- Error analysis and trends
- Daily quota snapshots
- ~300 lines with clear documentation

**Key Methods:**
```php
logRequest(...)              # Log each request
getAccountUsageStats(...)    # Get usage trends
getErrorAnalysis(...)        # Analyze failures
```

---

**[services/AIRequestHelper.php](services/AIRequestHelper.php)**
- Frontend-friendly integration helper
- Pre-built methods for common tasks
- No encryption key exposure
- Automatic initialization
- ~200 lines, very easy to use

**Key Methods:**
```php
generateQuestion(...)        # Generate chemistry questions
generateMCQOptions(...)      # Create multiple choice options
improveContent(...)          # Refine existing content
translateContent(...)        # Translate to other languages
customRequest(...)           # Flexible custom requests
```

---

#### Cron Jobs

**[cron/ai_daily_reset.php](cron/ai_daily_reset.php)**
- Runs at midnight (0 0 * * *)
- Resets daily quotas
- Auto-unblocks expired temporary blocks
- Creates daily snapshots
- Cleans old logs

---

**[cron/ai_health_check.php](cron/ai_health_check.php)**
- Runs every 15 minutes (*/15 * * * *)
- Tests key connectivity
- Records response times
- Auto-unblocks healthy keys
- Detects silent failures

---

#### Admin Interface

**[admin/api_ai_keys.php](admin/api_ai_keys.php)**
- REST API for administration
- List, add, disable keys
- Monitor account status
- View failover events
- Track usage statistics
- Check key health

**Endpoints:**
```
GET  ?action=list-keys
POST ?action=add-key
GET  ?action=accounts-status
GET  ?action=quota-usage
GET  ?action=failover-events
GET  ?action=keys-health
```

---

### Database

**[database/schema_ai_key_management.sql](database/schema_ai_key_management.sql)**
- Complete database schema (7 tables)
- Indexes for performance
- Sample account data
- Foreign key relationships
- Proper charset and collation

**Tables:**
1. `ai_accounts` - Account management
2. `ai_api_keys` - Individual keys (encrypted)
3. `ai_request_logs` - Audit trail
4. `ai_failover_events` - Failover tracking
5. `ai_account_quotas` - Daily snapshots
6. `ai_rate_limit_cache` - RPM/TPM tracking
7. `ai_key_health_checks` - Health check results

---

### Setup & Configuration

**[setup_ai_system.sh](setup_ai_system.sh)** (Linux/Mac)
- Automated setup script
- Generate encryption key
- Create directories
- Set permissions
- Cron job configuration

**[setup_ai_system.bat](setup_ai_system.bat)** (Windows)
- Batch script for Windows
- Generate encryption key
- Create directories
- Windows Task Scheduler instructions

---

## 🔍 Navigation by Use Case

### "I need to generate a question with AI"
1. See [IMPLEMENTATION_EXAMPLE.php](IMPLEMENTATION_EXAMPLE.php#L64-L90) - Example code
2. Copy `generateChemistryQuestion()` function
3. Call `AIRequestHelper::generateQuestion()`
4. Handle result and errors

---

### "I need to add a new API key"
1. Check [QUICK_REFERENCE.md](QUICK_REFERENCE.md#-common-tasks) - Add a New API Key
2. Use `AIKeyManager::storeNewKey()`
3. Or POST to `/admin/api_ai_keys.php?action=add-key`
4. Verify with `SELECT * FROM ai_api_keys`

---

### "I need to understand the failover logic"
1. Start: [QUICK_REFERENCE.md](QUICK_REFERENCE.md#-key-selection-algorithm-one-page) - Algorithm diagram
2. Detailed: [AI_KEY_ORCHESTRATION_SYSTEM.md](AI_KEY_ORCHESTRATION_SYSTEM.md#key-selection-algorithm) - Full explanation
3. Code: [services/AIKeyManager.php](services/AIKeyManager.php#L180-L250) - Implementation

---

### "I need to set up cron jobs"
1. Quick: [QUICK_REFERENCE.md](QUICK_REFERENCE.md#-cron-jobs) - Command templates
2. Detailed: [INTEGRATION_CHECKLIST.md](INTEGRATION_CHECKLIST.md#phase-4-set-up-cron-jobs) - Step-by-step
3. Reference: [AI_KEY_ORCHESTRATION_SYSTEM.md](AI_KEY_ORCHESTRATION_SYSTEM.md#daily-reset-job) - Full details

---

### "I need to monitor system health"
1. Quick: [QUICK_REFERENCE.md](QUICK_REFERENCE.md#-monitoring-queries) - SQL queries
2. API: [QUICK_REFERENCE.md](QUICK_REFERENCE.md#-admin-api-endpoints) - Admin endpoints
3. Full: [AI_KEY_ORCHESTRATION_SYSTEM.md](AI_KEY_ORCHESTRATION_SYSTEM.md#monitoring--analytics) - Detailed guide

---

### "Something isn't working"
1. Quick: [QUICK_REFERENCE.md](QUICK_REFERENCE.md#-troubleshooting) - Common issues
2. Full: [AI_KEY_ORCHESTRATION_SYSTEM.md](AI_KEY_ORCHESTRATION_SYSTEM.md#troubleshooting) - Detailed guide
3. Checklist: [INTEGRATION_CHECKLIST.md](INTEGRATION_CHECKLIST.md#troubleshooting-quick-reference)

---

### "I'm deploying to production"
1. Follow: [INTEGRATION_CHECKLIST.md](INTEGRATION_CHECKLIST.md) - All 10 phases
2. Reference: [AI_KEY_ORCHESTRATION_SYSTEM.md](AI_KEY_ORCHESTRATION_SYSTEM.md#security-considerations) - Security
3. Review: [AI_IMPLEMENTATION_SUMMARY.md](AI_IMPLEMENTATION_SUMMARY.md#production-readiness-checklist)

---

## 📊 Information Architecture

```
Documentation Index (this file)
├── Quick Reference (5 min)
│   └── Overview, common tasks, troubleshooting
├── Implementation Summary (10 min)
│   └── What was built, why, and how it works
├── Integration Checklist (20 min)
│   └── Step-by-step deployment guide
├── Complete Documentation (45 min)
│   └── Full details, architecture, security, monitoring
└── Implementation Example
    └── Real code samples and patterns
```

---

## 🎓 Learning Path

### Beginner (New to system)
1. [Quick Reference](QUICK_REFERENCE.md) - Get oriented (5 min)
2. [Implementation Summary](AI_IMPLEMENTATION_SUMMARY.md) - Understand (10 min)
3. [Implementation Example](IMPLEMENTATION_EXAMPLE.php) - See code (15 min)
4. **Total:** 30 minutes to basic understanding

---

### Intermediate (Implementing system)
1. Review [Integration Checklist](INTEGRATION_CHECKLIST.md) - Plan deployment (10 min)
2. Execute each phase following checklist (2-4 hours)
3. Test using [IMPLEMENTATION_EXAMPLE.php](IMPLEMENTATION_EXAMPLE.php) patterns (1 hour)
4. Deploy to production following phase 10 (30 min)
5. **Total:** 1-2 days for full implementation

---

### Advanced (Operating system)
1. Study [Complete Documentation](AI_KEY_ORCHESTRATION_SYSTEM.md) - All details (45 min)
2. Review [Code Implementation](services/) - Understand internals (1 hour)
3. Set up monitoring dashboards using Admin API (1 hour)
4. Plan capacity and scaling (30 min)
5. **Total:** 3 hours for expert knowledge

---

## 🔗 Cross-Reference Links

### By Topic

**Key Selection**
- [QUICK_REFERENCE.md - Algorithm](QUICK_REFERENCE.md#-key-selection-algorithm-one-page)
- [AI_KEY_ORCHESTRATION_SYSTEM.md - Full Details](AI_KEY_ORCHESTRATION_SYSTEM.md#key-selection-algorithm)
- [AIKeyManager.php - Implementation](services/AIKeyManager.php#L180-L250)

**Failure Handling**
- [QUICK_REFERENCE.md - Error Matrix](QUICK_REFERENCE.md#-error-handling-one-page)
- [AI_KEY_ORCHESTRATION_SYSTEM.md - Procedures](AI_KEY_ORCHESTRATION_SYSTEM.md#failure-handling)
- [AIGateway.php - Code](services/AIGateway.php#L150-L250)

**Encryption**
- [QUICK_REFERENCE.md - Security Checklist](QUICK_REFERENCE.md#-security-checklist)
- [AI_KEY_ORCHESTRATION_SYSTEM.md - Security](AI_KEY_ORCHESTRATION_SYSTEM.md#security-considerations)
- [AIKeyManager.php - Implementation](services/AIKeyManager.php#L64-L140)

**Monitoring**
- [QUICK_REFERENCE.md - Queries](QUICK_REFERENCE.md#-monitoring-queries)
- [QUICK_REFERENCE.md - Admin API](QUICK_REFERENCE.md#-admin-api-endpoints)
- [AI_KEY_ORCHESTRATION_SYSTEM.md - Full Guide](AI_KEY_ORCHESTRATION_SYSTEM.md#monitoring--analytics)

---

## 📝 Document Versions

| Document | Version | Updated | Purpose |
|----------|---------|---------|---------|
| QUICK_REFERENCE.md | 1.0 | 2026-01-28 | Quick lookup |
| AI_IMPLEMENTATION_SUMMARY.md | 1.0 | 2026-01-28 | Overview |
| AI_KEY_ORCHESTRATION_SYSTEM.md | 1.0 | 2026-01-28 | Complete reference |
| INTEGRATION_CHECKLIST.md | 1.0 | 2026-01-28 | Deployment guide |
| IMPLEMENTATION_EXAMPLE.php | 1.0 | 2026-01-28 | Code samples |

---

## ✅ How to Use This Index

1. **First time?** → Start with [Quick Reference](QUICK_REFERENCE.md)
2. **Need to implement?** → Follow [Integration Checklist](INTEGRATION_CHECKLIST.md)
3. **Need code examples?** → See [Implementation Example](IMPLEMENTATION_EXAMPLE.php)
4. **Need complete details?** → Read [Complete Documentation](AI_KEY_ORCHESTRATION_SYSTEM.md)
5. **Need quick answer?** → Use this index to find relevant section

---

## 📞 Getting Help

### For Quick Answers
→ Check [QUICK_REFERENCE.md](QUICK_REFERENCE.md)

### For Implementation Help
→ Follow [INTEGRATION_CHECKLIST.md](INTEGRATION_CHECKLIST.md)

### For Code Patterns
→ Study [IMPLEMENTATION_EXAMPLE.php](IMPLEMENTATION_EXAMPLE.php)

### For Everything Else
→ Consult [AI_KEY_ORCHESTRATION_SYSTEM.md](AI_KEY_ORCHESTRATION_SYSTEM.md)

---

**System Status:** ✅ Production Ready  
**Documentation Complete:** ✅ Yes  
**Ready to Deploy:** ✅ Yes  

---

**Start with:** [Quick Reference](QUICK_REFERENCE.md) (5 minutes)
