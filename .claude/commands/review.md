# Code Review: Recent Changes

Review all uncommitted or recently committed changes for quality, security, backwards compatibility, and convention compliance.

## Step 1: Gather changes
- Run `git diff` to see uncommitted changes
- If no uncommitted changes, run `git log -1 --format="%H" | xargs git diff HEAD~1` to review the last commit
- If the user specified a commit range in arguments, use that

## Step 2: Security Review
For each changed file, check:
- New user inputs are sanitized (`sanitize_text_field()`, `absint()`, etc.)
- New outputs are escaped (`esc_html()`, `esc_attr()`, etc.)
- New SQL queries use `$wpdb->prepare()`
- No new `eval()`, `exec()`, or similar dangerous functions
- No API keys or secrets hardcoded
- New REST endpoints have capability checks

## Step 3: Backwards Compatibility Review
- Are any existing function signatures changed?
- Are any REST API response fields renamed/removed?
- Are any wp_options keys renamed/removed?
- Are any database columns renamed/removed?
- Will existing users experience any change in behavior without opting in?

## Step 4: Convention Compliance
- Do new PHP classes/methods follow naming conventions (PascalCase classes, camelCase methods)?
- Do new Vue components follow PascalCase naming?
- Do new controller methods use `$this->response()` pattern?
- Are new strings wrapped in `__()` for translation?
- Are new API calls going through `Api.js` service?

## Step 5: Logic Review
- Are there any obvious bugs in the changed code?
- Are edge cases handled (null values, empty arrays, missing params)?
- Are error paths handled gracefully?
- Is there any code duplication that should be extracted?

## Step 6: CLAUDE.md Update Check
- Do these changes add/remove/rename any endpoints, views, controllers, models, services, or settings?
- If yes, flag that CLAUDE.md needs updating (or update it).

## Step 7: Report
```
=== Code Review Report ===

Files changed: X
Lines added: X | Lines removed: X

Security:       ✅ PASS / ❌ ISSUES
Compatibility:  ✅ PASS / ❌ BREAKING CHANGES
Conventions:    ✅ PASS / ⚠️ VIOLATIONS
Logic:          ✅ PASS / ⚠️ CONCERNS
CLAUDE.md:      ✅ Up to date / ⚠️ Needs update

ISSUES (must fix):
- [list]

SUGGESTIONS (optional improvements):
- [list]

VERDICT: ✅ Ready to commit / ❌ Needs changes
```
