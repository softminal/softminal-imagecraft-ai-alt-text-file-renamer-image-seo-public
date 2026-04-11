---
name: test
description: Run full test suite — PHP syntax, ESLint, build, class structure, route integrity, security scan
allowed-tools: Bash Read Grep Glob
---

# Full Test Suite

Run a comprehensive test of the ImageCraft plugin. Do ALL of the following steps in order:

## Step 1: PHP Syntax Check
Run `find includes/ -name "*.php" -exec php -l {} \;` to check all PHP files for syntax errors. Report any failures.

## Step 2: JavaScript/Vue Lint
Run `npm run lint` and report results. If there are errors (not warnings), list them with file:line.

## Step 3: Production Build
Run `npm run prod` and verify it completes without errors. Check that `assets/manifest.json` is generated.

## Step 4: PHP Class Structure Validation
Verify that:
- Every controller in `includes/Http/Controllers/` extends `Controller`
- Every model in `includes/Models/` extends `Model` (except `Model.php` itself and `Relations/`)
- Every AI provider in `includes/Services/AI/` extends `BaseAIProvider` (except `BaseAIProvider.php`, `AIFactory.php`, `ChatCompletionsProvider.php`)
- All classes have the correct namespace matching their directory path

## Step 5: REST API Route Integrity
Read `includes/Http/api.php` and verify:
- Every controller class referenced actually exists
- Every method referenced exists in that controller
- Every route has a capability check

## Step 6: Frontend Route Integrity
Read `resources/admin/router.js` and verify:
- Every imported component file exists
- No dead routes pointing to non-existent components

## Step 7: Security Quick Scan
Search the PHP codebase for common security issues:
- Direct use of `$_GET`, `$_POST`, `$_REQUEST` without sanitization
- `echo` statements without `esc_html()`, `esc_attr()`, or similar escaping
- SQL queries without `$wpdb->prepare()`
- Use of `eval()`, `exec()`, `system()`, `passthru()`, `shell_exec()`
- Unescaped output in any PHP file

## Step 8: Report
Provide a summary report in this format:
```
=== ImageCraft Test Report ===
PHP Syntax:     PASS / FAIL (X errors)
ESLint:         PASS / WARN (X warnings) / FAIL (X errors)
Build:          PASS / FAIL
Class Structure:PASS / FAIL (details)
Route Integrity:PASS / FAIL (details)
Frontend Routes:PASS / FAIL (details)
Security Scan:  PASS / WARN (X issues) / FAIL (X critical)
```

If anything fails, provide specific details on what to fix.
