---
name: security-audit
description: Deep WordPress security audit — sanitization, escaping, SQL injection, auth, XSS, OWASP top 10
allowed-tools: Bash Read Grep Glob
---

# WordPress Security Audit

Perform a thorough security audit of the ImageCraft plugin following WordPress.org review standards and OWASP top 10.

## 1. Input Sanitization Audit
Search ALL PHP files for:
- Any use of `$_GET`, `$_POST`, `$_REQUEST`, `$_SERVER`, `$_COOKIE` — these MUST be wrapped in sanitization functions
- Any `$request->get_param()` or `$request->get_json_params()` usage — verify the values are sanitized before use
- Check every controller method's parameter handling

For each finding, report: file:line, the problematic code, and the fix needed.

## 2. Output Escaping Audit
Search ALL PHP files for:
- `echo` statements that don't use `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses()`
- String concatenation in HTML output
- `printf`/`sprintf` with unescaped variables in HTML context

Note: REST API JSON responses via `wp_send_json_*` or `$this->response()` don't need escaping (JSON encoding handles it). Only flag HTML output.

## 3. SQL Injection Audit
Search ALL PHP files for:
- Direct `$wpdb->query()`, `$wpdb->get_results()`, `$wpdb->get_var()`, `$wpdb->get_row()` calls
- Verify ALL use `$wpdb->prepare()` when including dynamic values
- Check the Model base class query builder for proper escaping
- Flag any string concatenation inside SQL queries

## 4. Authentication & Authorization Audit
- Verify every REST route in `Http/api.php` has a `capability()` check
- Verify no endpoints are publicly accessible without authentication
- Check that nonce verification is properly handled
- Verify API key storage uses encryption (not plaintext)

## 5. File Operation Audit
- Check `FileRenamer.php` for path traversal vulnerabilities
- Verify file type validation before processing uploads
- Check for directory traversal (`../`) in any user-supplied file paths
- Verify no `file_get_contents()` on user-supplied URLs

## 6. External API Security
- Check all `wp_remote_post()`/`wp_remote_get()` calls in AI providers
- Verify API keys are never logged or exposed in responses
- Check for proper SSL verification (not disabled)
- Verify error messages don't leak API keys or internal details

## 7. XSS Audit (Frontend)
Search Vue files for:
- `v-html` usage — verify the content is sanitized or comes from trusted source
- Dynamic URLs that could be manipulated
- Any direct DOM manipulation with unsanitized data

## 8. Report
Provide a security report:
```
=== ImageCraft Security Audit ===
Input Sanitization:  PASS / X issues found
Output Escaping:     PASS / X issues found
SQL Injection:       PASS / X issues found
Auth & Permissions:  PASS / X issues found
File Operations:     PASS / X issues found
External APIs:       PASS / X issues found
XSS (Frontend):      PASS / X issues found

CRITICAL issues (fix immediately):
- [list]

WARNINGS (fix before next release):
- [list]

NOTES (low risk, consider fixing):
- [list]
```
