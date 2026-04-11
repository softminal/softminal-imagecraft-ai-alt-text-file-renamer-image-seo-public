---
name: monitor
description: Full codebase health check — dead code, convention violations, CLAUDE.md drift, dependency health
allowed-tools: Bash Read Grep Glob Edit
---

# Codebase Health Monitor

Run a full health check on the ImageCraft plugin codebase and report any issues, inconsistencies, or drift from conventions.

## 1. CLAUDE.md Drift Detection
Compare the current codebase against what CLAUDE.md documents:
- Are there any REST endpoints in `Http/api.php` that are NOT listed in the CLAUDE.md REST API table?
- Are there any Vue routes in `router.js` that are NOT listed in CLAUDE.md?
- Are there any controllers, models, or services that are NOT mentioned in CLAUDE.md's PHP Structure section?
- Are there any database tables in `Install.php` that are NOT listed in CLAUDE.md?

If any drift is found, update CLAUDE.md to match reality.

## 2. Dead Code Detection
Search for:
- PHP classes/files that are never `use`d or `require`d anywhere
- Vue components that are never imported or referenced
- JavaScript functions that are exported but never imported
- REST endpoints that are defined but have no frontend caller

## 3. Convention Violations
Check for:
- PHP files not following PascalCase naming
- Vue files not following PascalCase naming
- Methods not following camelCase
- Missing namespace declarations in PHP files
- Controllers not extending `Controller` base class
- Models not extending `Model` base class

## 4. Dependency Health
- Run `npm outdated` to check for outdated packages
- Check if `package-lock.json` is in sync with `package.json`
- Check for any npm audit vulnerabilities (`npm audit`)

## 5. Build Health
- Run `npm run prod` and verify it succeeds
- Check that `assets/manifest.json` exists and is valid JSON after build
- Verify no source maps are included in production build

## 6. Git Health
- Check for uncommitted changes
- Check for untracked files that should be tracked
- Check if `.gitignore` is missing any generated files

## 7. Report
```
=== ImageCraft Health Report ===

Documentation:    In sync / X items out of sync (auto-fixed)
Dead Code:        Clean / X unused items found
Conventions:      Consistent / X violations
Dependencies:     Healthy / X outdated / X vulnerable
Build:            Passing / Failing
Git:              Clean / Issues found

ACTION ITEMS:
- [prioritized list of things to fix]
```
