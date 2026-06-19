---
name: fatal-safety
description: Guarantee a code change can never cause a fatal error or take a user's site down — PHP syntax, uncaught throwables, missing guards on optional functions/classes, hook safety, activation/uninstall safety
allowed-tools: Bash Read Grep Glob
---

# Fatal-Error / Site-Down Safety Gate

**Goal:** This plugin is LIVE on WordPress.org on real production sites. A single
fatal error (`E_ERROR`, uncaught `\Error`/`\Throwable`, missing class/function)
in the wrong place can **white-screen a user's admin or public site**. This skill
verifies a code change cannot do that.

Run this on the **changed/uncommitted code**, not the whole codebase, unless
asked otherwise. Build the list of changed **and new** PHP files first — this
MUST include untracked files, which are the highest-risk case and which
`git diff` alone silently omits:

```bash
ICAIS_FILES=$( { git diff --name-only HEAD -- '*.php'; git ls-files --others --exclude-standard -- '*.php'; } | sort -u )
echo "$ICAIS_FILES"
```

Every grep step below scans `$ICAIS_FILES` (full content). New files are scanned
entirely; for modified files focus your judgement on the added lines. **Never use
`git diff HEAD | grep` for these scans — it drops untracked new files.**

Then work through every step. Report findings as **BLOCKER** (will or can fatal)
or **WARNING** (risky under some conditions). End with a clear verdict.

---

## Step 1: PHP syntax (hard blocker)

`php -l` every changed/new PHP file. Any syntax error is an automatic BLOCKER.

```bash
echo "$ICAIS_FILES" | while read -r f; do [ -n "$f" ] && php -l "$f"; done
```

## Step 2: Optional functions called without `function_exists()`

Calling a function that doesn't exist on the user's site = **immediate fatal**.
Any call to a function from an optional/version-gated dependency MUST be guarded
by `function_exists()` in the same code path (or called via a variable to also
satisfy Plugin Check — see `WP7Provider::clientPrompt()` / `isAvailable()`).

High-risk functions to grep for and verify are guarded:
- WordPress 7.0 AI Client: `wp_ai_client_prompt`, `wp_supports_ai`
- WooCommerce: `wc_get_product`, `WC()`, `wc_get_products`
- SEO plugins: Yoast/`WPSEO_*`, Rank Math/`rank_math`, AIOSEO, SEOPress functions
- FileBird, Kadence (`KADENCE_BLOCKS_VERSION`), Spectra (`UAGB_VER`)

```bash
echo "$ICAIS_FILES" | tr '\n' '\0' | xargs -0 grep -nE 'wp_ai_client_prompt|wp_supports_ai|wc_get_product|WPSEO|rank_math|WC\(' 2>/dev/null || echo "no high-risk function calls"
```
For each hit, confirm a `function_exists()` (or `defined()`) guard protects it.

## Step 3: Optional/external classes instantiated without `class_exists()`

`new \Some\External\Class()` where the class may not exist throws a fatal `\Error`
(NOT caught by `catch (\Exception)`). Any `new \…` into an external/optional
namespace MUST be preceded by `class_exists('\…')` OR be inside a
`try/catch (\Throwable)`. See `WP7Provider::generateAltText()`.

```bash
echo "$ICAIS_FILES" | tr '\n' '\0' | xargs -0 grep -nE 'new \\(WordPress|WooCommerce|Automattic)\\' 2>/dev/null || echo "no external-class instantiation"
```

## Step 4: `catch (\Exception)` that should be `catch (\Throwable)`

Code that touches **external libraries, reflection, or type-juggling** can throw
`\Error`/`\TypeError`, which `catch (\Exception)` does NOT catch. Around any
external-dependency call (AI Client, WooCommerce, SEO libs), the catch MUST be
`\Throwable`. Containing throwables at the integration boundary (e.g. inside
`WP7Provider`) protects every caller at once.

```bash
echo "$ICAIS_FILES" | tr '\n' '\0' | xargs -0 grep -nE 'catch \(\\?Exception' 2>/dev/null || echo "no catch(\Exception) present"
```
For each, ask: could the try-block raise a non-Exception `\Throwable`? If yes → BLOCKER, widen to `\Throwable`.

## Step 5: Hooks/callbacks that run broadly — must not throw

A fatal in a callback hooked to a broadly-firing or unattended action takes down
that whole request. Verify any new/changed callback on these is defensive
(guards + try/catch where it calls into anything fallible):
- `plugins_loaded`, `init`, `admin_init`, `current_screen`
- `add_attachment` and any `wp_schedule_single_event` / cron callback
- REST `permission_callback` and route handlers
- front-end hooks (anything not behind `is_admin()`)
- **activation, deactivation, and `uninstall.php`**

```bash
echo "$ICAIS_FILES" | tr '\n' '\0' | xargs -0 grep -nE 'add_action\(|register_activation_hook|register_uninstall_hook|wp_schedule_single_event' 2>/dev/null || echo "no broad hooks"
```
Front-end/public exposure of a fatal is the worst case — flag it as BLOCKER.

## Step 6: Null-safety and method calls

Model finders (`::find()`, `::getActive()`, `get_post()`, `wp_get_attachment_url()`)
can return `null`/`false`. Calling a method/property on the result without a null
check can fatal. Verify new dereferences are guarded.

## Step 7: PHP 7.4 compatibility (minimum supported)

The plugin's floor is PHP 7.4. Do NOT introduce 8.0+-only syntax: enums,
`readonly`, named arguments in calls to plugin code, `mixed`/union return types,
`str_contains`/`str_starts_with` without polyfill, first-class callable syntax,
constructor property promotion is 8.0+. Flag any of these as BLOCKER.

## Step 8: Backwards-compatibility cross-check

Confirm the change does not break sites that **upgrade** (see CLAUDE.md "Live
Plugin" rules): no renamed/removed `wp_options` keys, REST fields, DB columns;
new settings default to old behavior; DB schema changes are version-gated in
`Install.php` and handle fresh-install AND upgrade.

---

## Report format

```
=== Fatal-Safety Report ===
Files checked: <list>

BLOCKERS (will / can take a site down — MUST fix before shipping):
- <file:line> — <why it can fatal> → <fix>

WARNINGS (risky under some conditions):
- <file:line> — <why> → <suggested guard>

VERDICT: SAFE TO SHIP / NOT SAFE — <one line>
```

If there are zero BLOCKERS, state plainly that the change cannot fatal a user's
site for the reasons checked. If any BLOCKER exists, do not call the change safe.
