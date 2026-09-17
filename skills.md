# Project Skills Index for AI Coding Agents

> **Repository:** AhmadLearninghub / Question Paper Management & Quiz System  
> **Global Skills Root:** `C:\Users\touse\.agents\skills`  
> **Last Updated:** 2026-09-17

---

## Instructions for AI Coding Agents

When working on this repository, **before writing, refactoring, or reviewing code**, you MUST check the relevant category below and consult the corresponding skill's `SKILL.md` file using `view_file`.

1. **Identify the Task Domain**: Determine whether your task involves PHP backend logic, MySQL database queries, security/auth hardening, Pakistani payment gateways (Safepay/JazzCash), frontend quiz UI, SEO/AdSense, or debugging.
2. **Open the Corresponding Skill**: Use `view_file` to read the `SKILL.md` file at the specified absolute path.
3. **Follow the Standard**: Strictly adhere to the architecture principles, security checklists, coding standards, and validation workflows defined in the skill before completing the task.

---

## 1. PHP & Backend Engineering Core

Use these skills when writing or refactoring server-side logic in `auth/`, `admin/`, `quiz/`, `examPreparation/`, `services/`, `repositories/`, and root controller scripts.

| Skill Name | Path to `SKILL.md` | When to Use in This Project |
| :--- | :--- | :--- |
| **`php-pro`** | [`C:\Users\touse\.agents\skills\php-pro\SKILL.md`](file:///C:/Users/touse/.agents/skills/php-pro/SKILL.md) | Writing clean, modern PHP, handling typing, OOP structure, exception handling, and standard library best practices. |
| **`backend-dev-guidelines`** | [`C:\Users\touse\.agents\skills\backend-dev-guidelines\SKILL.md`](file:///C:/Users/touse/.agents/skills/backend-dev-guidelines/SKILL.md) | Designing modular backend workflows, separation of concerns between repositories, controllers, and services. |
| **`clean-code`** | [`C:\Users\touse\.agents\skills\clean-code\SKILL.md`](file:///C:/Users/touse/.agents/skills/clean-code/SKILL.md) | Eliminating code smells, improving naming conventions, adhering to SOLID principles, and minimizing function complexity. |
| **`laravel-expert`** | [`C:\Users\touse\.agents\skills\laravel-expert\SKILL.md`](file:///C:/Users/touse/.agents/skills/laravel-expert/SKILL.md) | Reference patterns for repository patterns, query builders, dependency injection, and modern MVC architecture. |

---

## 2. Database & MySQL Optimization

Use these skills when modifying `db_connect.php`, writing SQL queries, working with `database/` and `migrations/`, or handling question paper generation queries with complex JOINs and filters.

| Skill Name | Path to `SKILL.md` | When to Use in This Project |
| :--- | :--- | :--- |
| **`sql-pro`** | [`C:\Users\touse\.agents\skills\sql-pro\SKILL.md`](file:///C:/Users/touse/.agents/skills/sql-pro/SKILL.md) | Writing complex queries for MCQs, question selections, chapter filtering, and aggregation. |
| **`sql-optimization-patterns`** | [`C:\Users\touse\.agents\skills\sql-optimization-patterns\SKILL.md`](file:///C:/Users/touse/.agents/skills/sql-optimization-patterns/SKILL.md) | Optimizing slow queries, index design for high-traffic question paper generation, and schema tuning. |
| **`sql-sentinel`** | [`C:\Users\touse\.agents\skills\sql-sentinel\SKILL.md`](file:///C:/Users/touse/.agents/skills/sql-sentinel/SKILL.md) | Preventing SQL vulnerabilities and enforcing prepared statements across all PHP query layers. |
| **`database-design`** | [`C:\Users\touse\.agents\skills\database-design\SKILL.md`](file:///C:/Users/touse/.agents/skills/database-design/SKILL.md) | Designing new relational tables, normalization, foreign keys, and referential integrity for classes, subjects, and exams. |
| **`database-optimizer`** | [`C:\Users\touse\.agents\skills\database-optimizer\SKILL.md`](file:///C:/Users/touse/.agents/skills/database-optimizer/SKILL.md) | Analyzing query execution plans (`EXPLAIN`), connection pooling, and memory caching strategies. |
| **`database-migration`** | [`C:\Users\touse\.agents\skills\database-migration\SKILL.md`](file:///C:/Users/touse/.agents/skills/database-migration/SKILL.md) | Designing safe, reversible schema migrations in `migrations/` without data loss or downtime. |

---

## 3. Security, Authentication & OWASP Hardening

Use these skills when handling user inputs, session validation, passwords, role-based access control (`admin/` vs `student`), upload handling, or payment callbacks.

| Skill Name | Path to `SKILL.md` | When to Use in This Project |
| :--- | :--- | :--- |
| **`web-security-testing`** | [`C:\Users\touse\.agents\skills\web-security-testing\SKILL.md`](file:///C:/Users/touse/.agents/skills/web-security-testing/SKILL.md) | Comprehensive web penetration testing, OWASP Top 10 auditing, and finding vulnerabilities in PHP endpoints. |
| **`security-audit`** | [`C:\Users\touse\.agents\skills\security-audit\SKILL.md`](file:///C:/Users/touse/.agents/skills/security-audit/SKILL.md) | Auditing sensitive code paths, session handling, upload vectors, and admin privilege escalation risks. |
| **`backend-security-coder`** | [`C:\Users\touse\.agents\skills\backend-security-coder\SKILL.md`](file:///C:/Users/touse/.agents/skills/backend-security-coder/SKILL.md) | Writing defensive PHP code against request tampering, parameter pollution, and unsafe deserialization. |
| **`sql-injection-testing`** | [`C:\Users\touse\.agents\skills\sql-injection-testing\SKILL.md`](file:///C:/Users/touse/.agents/skills/sql-injection-testing/SKILL.md) | Testing all dynamic inputs (POST/GET parameters in `select_question.php`, search bars) against SQL injection. |
| **`xss-html-injection`** | [`C:\Users\touse\.agents\skills\xss-html-injection\SKILL.md`](file:///C:/Users/touse/.agents/skills/xss-html-injection/SKILL.md) | Ensuring proper `htmlspecialchars()`, Content Security Policy (CSP), and client-side sanitization on questions/notes. |
| **`broken-authentication`** | [`C:\Users\touse\.agents\skills\broken-authentication\SKILL.md`](file:///C:/Users/touse/.agents/skills/broken-authentication/SKILL.md) | Securing session cookies, remember-me tokens, password hashing (`password_hash`), and OAuth callbacks. |
| **`auth-implementation-patterns`** | [`C:\Users\touse\.agents\skills\auth-implementation-patterns\SKILL.md`](file:///C:/Users/touse/.agents/skills/auth-implementation-patterns/SKILL.md) | Standardizing user registration, password resets, role checks, and token expiry. |
| **`idor-testing`** | [`C:\Users\touse\.agents\skills\idor-testing\SKILL.md`](file:///C:/Users/touse/.agents/skills/idor-testing/SKILL.md) | Preventing Insecure Direct Object References (e.g. students accessing tests, notes, or profile data of other users). |
| **`security-and-hardening`** | [`C:\Users\touse\.agents\skills\security-and-hardening\SKILL.md`](file:///C:/Users/touse/.agents/skills/security-and-hardening/SKILL.md) | General server hardening, `.htaccess` rule tightening, directory traversal defense on downloads/uploads. |

---

## 4. Payment Gateways & Subscription Checkout

Use these skills when modifying or expanding payment processing in `payment/`, `subscription.php`, and `safepay-php-main/`.

| Skill Name | Path to `SKILL.md` | When to Use in This Project |
| :--- | :--- | :--- |
| **`pakistan-payments-stack`** | [`C:\Users\touse\.agents\skills\pakistan-payments-stack\SKILL.md`](file:///C:/Users/touse/.agents/skills/pakistan-payments-stack/SKILL.md) | Integrating and debugging Pakistani payment rails: Safepay, JazzCash, Easypaisa, 1Link, and Raast with webhook verification. |
| **`payment-integration`** | [`C:\Users\touse\.agents\skills\payment-integration\SKILL.md`](file:///C:/Users/touse/.agents/skills/payment-integration/SKILL.md) | Robust subscription flows, payment webhook reconciliation, idempotent transactions, and failure recovery. |

---

## 5. Frontend UI/UX, CSS & Quiz Interactivity

Use these skills when developing or refining interfaces in `css/`, `js/`, `quiz/`, `examPreparation/`, `header.php`, `footer.php`, and printable paper views.

| Skill Name | Path to `SKILL.md` | When to Use in This Project |
| :--- | :--- | :--- |
| **`frontend-developer`** | [`C:\Users\touse\.agents\skills\frontend-developer\SKILL.md`](file:///C:/Users/touse/.agents/skills/frontend-developer/SKILL.md) | Implementing modern, accessible, and performant frontend logic and DOM interactions. |
| **`frontend-design`** | [`C:\Users\touse\.agents\skills\frontend-design\SKILL.md`](file:///C:/Users/touse/.agents/skills/frontend-design/SKILL.md) | Crafting thoughtful, production-grade visual layouts and printable question paper layouts. |
| **`ui-ux-pro-max`** | [`C:\Users\touse\.agents\skills\ui-ux-pro-max\SKILL.md`](file:///C:/Users/touse/.agents/skills/ui-ux-pro-max/SKILL.md) | Comprehensive UI/UX guide for student exam taking, timers, score cards, and responsive forms. |
| **`web-design-guidelines`** | [`C:\Users\touse\.agents\skills\web-design-guidelines\SKILL.md`](file:///C:/Users/touse/.agents/skills/web-design-guidelines/SKILL.md) | Validating layout consistency, mobile responsiveness, touch targets, and typography standards. |
| **`design-taste-frontend`** | [`C:\Users\touse\.agents\skills\design-taste-frontend\SKILL.md`](file:///C:/Users/touse/.agents/skills/design-taste-frontend/SKILL.md) | Applying premium color palettes, subtle micro-interactions, and eliminating cluttered layouts. |
| **`baseline-ui`** | [`C:\Users\touse\.agents\skills\baseline-ui\SKILL.md`](file:///C:/Users/touse/.agents/skills/baseline-ui/SKILL.md) | Rapidly cleaning up uneven margins, misaligned table borders, and spacing inconsistencies. |
| **`javascript-pro`** | [`C:\Users\touse\.agents\skills\javascript-pro\SKILL.md`](file:///C:/Users/touse/.agents/skills/javascript-pro/SKILL.md) | Writing modular vanilla JS for interactive quiz timers, AJAX question loading, and dynamic chapter selectors. |
| **`modern-javascript-patterns`** | [`C:\Users\touse\.agents\skills\modern-javascript-patterns\SKILL.md`](file:///C:/Users/touse/.agents/skills/modern-javascript-patterns/SKILL.md) | Modern ES6+ patterns, async/await fetch calls, and error boundaries for client-side scripts. |

---

## 6. SEO, Google AdSense Compliance & Performance

Use these skills when updating meta tags, `generate_sitemap.php`, `robots.txt`, `ADSENSE_REVIEW.md`, or improving page load speed.

| Skill Name | Path to `SKILL.md` | When to Use in This Project |
| :--- | :--- | :--- |
| **`seo`** | [`C:\Users\touse\.agents\skills\seo\SKILL.md`](file:///C:/Users/touse/.agents/skills/seo/SKILL.md) | Full SEO audit of education content, chapter guides, and past question papers. |
| **`seo-technical`** | [`C:\Users\touse\.agents\skills\seo-technical\SKILL.md`](file:///C:/Users/touse/.agents/skills/seo-technical/SKILL.md) | Crawlability, indexability, structured data (Schema.org `Quiz`, `EducationalWebPage`), and robots indexing. |
| **`seo-sitemap`** | [`C:\Users\touse\.agents\skills\seo-sitemap\SKILL.md`](file:///C:/Users/touse/.agents/skills/seo-sitemap/SKILL.md) | Managing dynamic XML sitemaps for classes, subjects, and newly published notes. |
| **`seo-meta-optimizer`** | [`C:\Users\touse\.agents\skills\seo-meta-optimizer\SKILL.md`](file:///C:/Users/touse/.agents/skills/seo-meta-optimizer/SKILL.md) | Crafting high-CTR titles and meta descriptions for education and exam queries. |
| **`web-performance-optimization`** | [`C:\Users\touse\.agents\skills\web-performance-optimization\SKILL.md`](file:///C:/Users/touse/.agents/skills/web-performance-optimization/SKILL.md) | Server response time optimization, caching headers in `.htaccess`, asset minification, and image compression. |
| **`frontend-lighthouse`** | [`C:\Users\touse\.agents\skills\frontend-lighthouse\SKILL.md`](file:///C:/Users/touse/.agents/skills/frontend-lighthouse/SKILL.md) | Measuring Core Web Vitals (LCP, FID/INP, CLS) to pass Google AdSense policy standards. |

---

## 7. Docker & Environment Deployment

Use these skills when editing `Dockerfile`, `docker-compose.yml`, `Setup-DockerFresh.ps1`, or Apache/PHP server configurations.

| Skill Name | Path to `SKILL.md` | When to Use in This Project |
| :--- | :--- | :--- |
| **`docker-expert`** | [`C:\Users\touse\.agents\skills\docker-expert\SKILL.md`](file:///C:/Users/touse/.agents/skills/docker-expert/SKILL.md) | Multi-stage Docker builds, PHP extension configuration (pdo_mysql, gd, zip), and container security. |
| **`environment-setup-guide`** | [`C:\Users\touse\.agents\skills\environment-setup-guide\SKILL.md`](file:///C:/Users/touse/.agents/skills/environment-setup-guide/SKILL.md) | Documenting and troubleshooting local development environments (XAMPP / Docker / Windows PowerShell). |

---

## 8. Debugging, Code Review & Refactoring

Use these skills before submitting pull requests, fixing bugs, or refactoring legacy scripts like `generate_question_paper.php`.

| Skill Name | Path to `SKILL.md` | When to Use in This Project |
| :--- | :--- | :--- |
| **`code-reviewer`** | [`C:\Users\touse\.agents\skills\code-reviewer\SKILL.md`](file:///C:/Users/touse/.agents/skills/code-reviewer/SKILL.md) | Conducting strict code reviews on PHP, JS, and SQL changes before merge. |
| **`code-review-excellence`** | [`C:\Users\touse\.agents\skills\code-review-excellence\SKILL.md`](file:///C:/Users/touse/.agents/skills/code-review-excellence/SKILL.md) | Systematic review checklists focused on correctness, edge cases, and maintainability. |
| **`systematic-debugging`** | [`C:\Users\touse\.agents\skills\systematic-debugging\SKILL.md`](file:///C:/Users/touse/.agents/skills/systematic-debugging/SKILL.md) | Isolating root causes of PHP runtime errors, unexpected question paper outputs, or session dropouts. |
| **`debugging-code`** | [`C:\Users\touse\.agents\skills\debugging-code\SKILL.md`](file:///C:/Users/touse/.agents/skills/debugging-code/SKILL.md) | Step-by-step diagnostic workflows, error log analysis in `logs/`, and variable trace methods. |
| **`code-refactoring-refactor-clean`** | [`C:\Users\touse\.agents\skills\code-refactoring-refactor-clean\SKILL.md`](file:///C:/Users/touse/.agents/skills/code-refactoring-refactor-clean/SKILL.md) | Safely breaking down oversized PHP files into reusable classes and helper functions without breaking existing behavior. |

---

## Quick Workflow Example for AI Agents

```bash
# Example: If tasked with fixing SQL queries in question generation:
1. Open and read: C:\Users\touse\.agents\skills\sql-pro\SKILL.md
2. Open and read: C:\Users\touse\.agents\skills\sql-sentinel\SKILL.md
3. Apply prepared statement standards and indexing best practices to db_connect.php and select_question.php.
4. Review against: C:\Users\touse\.agents\skills\code-reviewer\SKILL.md
```
