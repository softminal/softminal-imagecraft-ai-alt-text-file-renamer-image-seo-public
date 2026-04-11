---
name: pre-release
description: Complete release gate before shipping to WordPress.org — version sync, tests, security, compliance
allowed-tools: Bash Read Grep Glob
---

# Pre-Release Validation

Run ALL checks required before releasing a new version to WordPress.org. This is the final gate before shipping.

## Step 1: Version Consistency
Check that the version number is consistent across:
- `imagecraft-ai-alt-text-file-renamer-image-seo.php` (plugin header `Version:`)
- `readme.txt` (`Stable tag:`)
- `config/constants.php` (`ICAIS_VERSION`)
- `package.json` (`version`)

Flag any mismatches.

## Step 2: Full Test Suite
Run everything from the `/test` command:
- PHP syntax check
- ESLint (must have 0 errors)
- Production build (must succeed)
- Class structure validation
- Route integrity check
- Frontend route integrity

## Step 3: Security Audit
Run everything from the `/security-audit` command. ALL critical issues must be resolved before release.

## Step 4: Backwards Compatibility Check
- Read `Install.php` — if there are new database migrations, verify they handle both fresh install AND upgrade paths
- Check `Settings.php` `$defaults` — new settings must have safe defaults
- Search for any removed/renamed wp_options keys, REST fields, or DB columns since last release

## Step 5: WordPress.org Compliance
- Verify `readme.txt` has valid format (check `Tested up to`, `Requires PHP`, `Stable tag`)
- Verify no development files will ship (check `.distignore` covers all dev files)
- Verify no minified JS without source (Vite manifest handles this)
- Verify no external CDN dependencies loaded without fallback
- Verify all text strings use the correct text domain: `imagecraft-ai-alt-text-file-renamer-image-seo`

## Step 6: Build & Package
- Run `npm run prod` — verify clean build
- Run `./build.sh` — verify ZIP is created
- Check ZIP contents — verify no dev files leaked in

## Step 7: Changelog
- Check `readme.txt` changelog section — is the new version documented?
- Are all user-facing changes listed?

## Step 8: Report
```
=== Pre-Release Report: v[VERSION] ===

Version Sync:     PASS / MISMATCH
Test Suite:       PASS / FAIL
Security:         PASS / ISSUES
Compatibility:    PASS / BREAKING
WP.org Compliance:PASS / ISSUES
Build & Package:  PASS / FAIL
Changelog:        PASS / INCOMPLETE

BLOCKERS (must fix before release):
- [list]

WARNINGS (should fix):
- [list]

VERDICT: Ready to release / NOT ready
```
