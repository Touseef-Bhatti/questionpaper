# Google AdSense Readiness Review

**Site:** https://ahmadlearninghub.com.pk/  
**Audit date:** June 14, 2026  
**Review scope:** Live homepage, About, Contact, question-paper generator, MCQs setup, study-material pages, and the current local PHP codebase.

## Executive Verdict

**Current status: Not ready to apply yet.**

**Estimated approval chance if submitted now: 30-40%.**

**Estimated approval chance after completing all critical fixes: 70-85%.**

This percentage is an informed estimate, not a guarantee. Google does not publish an approval formula, and a reviewer may consider account history, crawlability, traffic quality, content ownership, and pages not sampled in this audit.

The site has real educational tools, substantial subject content, About/Contact/legal pages, HTTPS, mobile-oriented layouts, and a working navigation system. These are strong foundations. The main risks are the third-party rewarded ad gate, weak consent/privacy implementation, unsupported promotional claims, inconsistent identity/contact details, index-quality problems, intrusive popup load, and publicly deployable test/installation scripts.

## Readiness Score

| Area | Score | Assessment |
|---|---:|---|
| Original value and useful tools | 16/20 | Strong question-paper, quiz, notes, and test utilities |
| Content quality and credibility | 10/20 | Good volume, but keyword-heavy copy and unsupported claims reduce trust |
| Navigation and user experience | 10/15 | Main sections are reachable, but multiple modals and ad gates interrupt users |
| Trust and transparency | 7/15 | Required pages exist, but business details and emails are inconsistent |
| Policy and privacy readiness | 5/15 | Privacy page and cookie consent are incomplete for advertising use |
| Technical and crawl readiness | 7/15 | HTTPS and sitemap exist, but duplicate/action URLs and exposed scripts are risks |
| **Total** | **55/100** | **Fix critical issues before applying** |

## Critical Issues

### 1. Remove the third-party "Watch Ad" gate before applying

`includes/quiz_ad_gate.php` asks users to watch an ad to unlock one hour of quiz access and opens:

`https://omg10.com/4/10866850`

This is the largest approval and long-term account risk. The implementation rewards an ad visit with access, attracts attention to the ad, and opens a separate advertising page. Standard AdSense policy prohibits encouraging or compensating users for viewing ordinary ads. Unwanted pop-ups, pop-unders, or redirects can also violate site-behavior requirements.

**Required action:** Disable this gate throughout the public site before requesting review. Do not replace its URL with an AdSense ad. Only use an official Google-supported rewarded product if the account and format explicitly permit it.

### 2. Replace the current cookie banner with real consent controls

The current banner has only an **Accept Cookies** button. Google Analytics is included before the user accepts. There is no Reject button, preference management, or prior blocking of non-essential cookies.

**Required action:**

- Add **Accept**, **Reject**, and **Manage preferences** choices.
- Do not load analytics or personalized advertising before consent where consent is legally required.
- Use a Google-certified consent management platform for AdSense traffic from the EEA, UK, and Switzerland.
- Store and respect the user's choice.
- Add a permanent "Cookie settings" link in the footer.

### 3. Rewrite and expand the Privacy Policy

The Privacy Policy is too general for an advertising-supported website. It does not clearly identify Google AdSense, advertising cookies, Google/third-party vendors, personalized advertising, consent controls, opt-out choices, retention periods, or children's data practices.

It also displays `Last Updated: current date`, which changes automatically every day even when the policy has not changed. This looks unreliable.

**Required action:**

- Use a fixed, truthful last-revised date.
- Name Google Analytics and Google AdSense separately.
- Explain advertising cookies and personalized/non-personalized ads.
- Link to Google's advertising/privacy controls.
- Explain account data, quiz data, uploaded files, AI-provider processing, payment data, retention, deletion, security, and contact procedures.
- Add a children's privacy section because the service targets school students.

### 4. Remove or prove promotional statistics and broad coverage claims

The live site claims:

- `50,000+ MCQs`
- `98% Success Rate`
- `5,000+ Daily Quizzes`
- `98% Accuracy`
- "thousands of learners and educators worldwide"
- coverage for CBSE, ICSE, NEET, MCAT, JEE, GRE, GMAT, SAT, IELTS, TOEFL, and other exams

These claims should be supported by real analytics, database counts, methodology, and matching content. Broad claims that are not demonstrably true weaken publisher credibility and may look misleading or automatically generated for search traffic.

**Required action:** Replace unsupported numbers with verifiable dynamic counts or remove them. Narrow exam-board claims to subjects and boards that the database genuinely supports.

### 5. Standardize publisher identity and contact information

Different pages/code locations use:

- Sheikhupura and Gujranwala
- `admin@ahmadlearninghub.com.pk`
- `support@ahmadlearninghub.com`
- `touseef12345bhatt@gmail.com`
- `zouraize@gmail.com`
- different WhatsApp numbers

Generic Facebook, Twitter, and LinkedIn homepage links are also shown as if they are official profiles.

**Required action:** Use one official business name, founder/operator name, location, domain email, phone number, and support route everywhere. Remove empty/generic social links until real profiles exist.

### 6. Block development, test, migration, and installation scripts

The repository contains approximately 39 files with names such as:

- `install.php`
- `test_mailhog.php`
- `tests/test.php`
- `tests/debug_env.php`
- `quiz/show_mcqs_table.php`
- `quiz/setup_quiz_enhancements.php`
- `payment/run_migration.php`
- database fix/migration scripts

The root `.htaccess` currently protects environment and archive files but does not broadly deny these utilities. Even if some scripts perform their own checks, their presence on production creates quality and security concerns.

**Required action:** Remove them from production or deny public access at the server level. Return `404` or `403`, and keep admin/migration tools behind strong authentication.

## High-Priority Weak Points

### Content reads as keyword-targeted in several places

Repeated phrases such as "Online question paper generator," "chapter wise question paper generator," and long lists of boards/exams make some pages sound written for search engines instead of students. Some grammar and spelling issues are visible, including "Quizez," "System-generat," spacing before punctuation, and inconsistent "9 th/9th" formatting.

**Suggestion:** Edit every important landing page for natural language, factual accuracy, and clear educational usefulness. Prefer worked examples, syllabus details, teacher guidance, screenshots, author/reviewer attribution, and update notes over repeated keywords.

### Some content is outdated

The study-material page states academic year `2024-2025` while also saying the content is aligned with the latest curriculum. The current audit date is June 14, 2026.

**Suggestion:** Audit all year and syllabus claims. Show a genuine content review date and identify the applicable board, textbook edition, and class.

### Too many interruptions before users reach content

Live pages can expose a study-level selector, login prompt, premium upgrade prompt, cookie banner, and on some flows an ad-unlock modal. Even when each component has a purpose, their combined effect can obscure the main content and reduce trust.

**Suggestion:** During AdSense review, show the educational content immediately. Limit the experience to one necessary prompt at a time and avoid automatic promotional modals on About, Contact, Privacy, and Terms pages.

### Sitemap includes weak or non-content URLs

`generate_sitemap.php` includes duplicate `.php` and clean URLs, workflow/action pages, quiz lobby/take/dashboard pages, and AI generation endpoints. Some of these pages may require state, authentication, query parameters, or generated data and may be thin or unusable to a crawler.

Examples include:

- `/index.php` and `/`
- `/generate_question_paper.php`
- `/questionPaperFromTopic/generate_ai_paper.php`
- `/quiz/online_quiz_dashboard.php`
- `/quiz/online_quiz_lobby.php`
- `/quiz/online_quiz_take.php`

**Suggestion:** Include only canonical, public, indexable pages that return useful standalone content with HTTP 200. Remove action endpoints, private pages, duplicates, empty combinations, and session-dependent pages.

### Canonical and indexing controls need a sitewide audit

Canonical tags are not consistently visible across the PHP templates, while the same content may be available through `.php`, extensionless, rewritten, query-string, uppercase, and lowercase URLs.

**Suggestion:** Add one self-referencing canonical URL to every indexable page. Redirect duplicate URL forms with 301 responses. Add `noindex,follow` to account, search, internal workflow, generated-preview, lobby, dashboard, and other low-value state pages.

### Robots.txt is incomplete

The file blocks several private folders, but it does not address the test, installation, migration, payment utility, cron, storage, email, and other sensitive paths. Robots rules do not provide security, but they help prevent crawl waste after server access is secured.

**Suggestion:** First deny access with server/auth controls, then update `robots.txt` to exclude non-public application areas. Do not use robots.txt as the only protection.

### ads.txt is not present in the repository

This is not normally the main reason for initial site rejection, and the correct line cannot be created until Google gives the publisher ID.

**Suggestion:** After the AdSense account provides the publisher ID, publish the exact Google-authorized line at:

`https://ahmadlearninghub.com.pk/ads.txt`

### Legal and support wording is inconsistent

The Terms page uses `support@ahmadlearninghub.com`, while the public site domain is `.com.pk`. The refund statement says all payments are non-refundable unless stated otherwise, but there is no clear refund/cancellation policy linked near checkout.

**Suggestion:** Add a clear refund/cancellation policy, subscription terms, AI-content disclaimer, copyright/takedown process, and consistent support contact.

## Strengths

- The website provides functional educational tools rather than only short articles.
- Core navigation exposes Generate Paper, quizzes, notes, About, Contact, Privacy, and Terms.
- HTTPS is active.
- About and Contact pages identify a founder and educational purpose.
- The site has substantial class, subject, chapter, quiz, and study-resource coverage.
- Many pages contain useful explanatory text and FAQs.
- The footer makes trust pages easy to reach.
- Monetag in-page and vignette allowlists are currently empty in `includes/monetag_ads.php`, so those two placements are disabled by the local code.

## Recommended Approval Plan

### Phase 1: Must complete before application

1. Remove the third-party Watch Ad gate and any pop-under, forced redirect, or rewarded ordinary ad.
2. Replace unsupported statistics and broad exam claims.
3. Standardize all contact, location, founder, phone, and email information.
4. Upgrade the Privacy Policy and cookie consent system.
5. Remove or server-block all installation, test, debug, migration, and maintenance scripts.
6. Fix broken/generic social links and visible spelling/encoding errors.
7. Ensure policy pages open without login, upgrade, role-selection, or ad prompts covering the content.

### Phase 2: Improve crawl and content quality

1. Clean the sitemap so it contains only canonical public content pages.
2. Add canonical tags and 301 redirects for duplicate URL forms.
3. Add `noindex` to thin, private, generated, and workflow pages.
4. Refresh outdated curriculum/year references.
5. Rewrite keyword-heavy landing-page copy with natural, evidence-based educational content.
6. Add author/editor details and genuine review/update dates to major educational resources.
7. Check every sitemap URL for HTTP 200, useful visible content, and no PHP warnings.

### Phase 3: Final checks

1. Test the site logged out in desktop and mobile views.
2. Run Google Search Console URL Inspection on the homepage and key content pages.
3. Confirm there are no manual actions or security issues.
4. Confirm the AdSense crawler is not blocked by login, robots rules, firewall, or geolocation controls.
5. Check Core Web Vitals and remove unnecessary third-party scripts.
6. Apply only after the cleaned site has been live and crawlable long enough for Google to reprocess it.
7. Add the correct `ads.txt` entry when AdSense supplies the publisher ID.

## Final Assessment

Ahmad Learning Hub has enough genuine functionality and educational subject matter to become an AdSense-quality site. The problem is not a simple lack of page count. The current risk comes from trust, policy implementation, aggressive monetization behavior, exaggerated claims, and crawl hygiene.

Do **not** apply in the current state. Complete the Phase 1 fixes first, then clean the indexable URL set and content claims. With those changes, the site should move from a high-risk submission to a reasonably strong candidate, though final approval always remains Google's decision.

## Official Google References

- [Eligibility requirements for AdSense](https://support.google.com/adsense/answer/9724)
- [Make sure your site's pages are ready for AdSense](https://support.google.com/adsense/answer/7299563)
- [AdSense Program policies](https://support.google.com/adsense/answer/48182)
- [Google Publisher Policies](https://support.google.com/adsense/answer/10502938)
- [Comply with the EU user consent policy](https://support.google.com/adsense/answer/7670013)
- [Ads.txt guide](https://support.google.com/adsense/answer/12171612)
