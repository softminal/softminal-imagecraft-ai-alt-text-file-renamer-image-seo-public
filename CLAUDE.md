# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## SELF-MAINTENANCE RULE

**This file MUST be kept up-to-date.** After any of the following changes, update the relevant sections of this file:
- Adding/removing/renaming a REST API endpoint → update REST API table
- Adding/removing/renaming a Vue route or view → update Frontend Structure + Dependency Map
- Adding/removing/renaming a PHP controller, model, or service → update PHP Structure + Dependency Map
- Adding/removing a database table or column → update Database Tables
- Adding/removing a wp_options key → update Key Constants / Development Notes
- Adding/removing a new feature module → update Dependency Map
- Changing AI provider defaults or adding a provider → update AI Provider Details
- Adding/removing npm scripts or dev tooling → update Commands section

If you are unsure whether a change warrants an update, update it anyway. **Outdated documentation is worse than no documentation.**

### Also update PROJECT_STATE.md

`PROJECT_STATE.md` (project root) is a living, at-a-glance snapshot of the
project's features, codebase map, and runtime flows. **It MUST be kept current.**
After any feature add/remove/change, REST endpoint, Vue view/route, PHP
controller/service/model, DB table/column, `wp_options` key, AI provider, or
runtime-flow change:
1. Update the relevant section(s) of `PROJECT_STATE.md`.
2. Prepend a dated entry to its **Recent Changes** section.
3. Keep it in sync with this file (CLAUDE.md remains authoritative for the full
   dependency map, REST table, and conventions).

---

## Project Overview

**ImageCraft** is a WordPress plugin that generates AI-powered alt text for images using Anthropic Claude, OpenAI, or Google Gemini APIs. It follows a BYOK (Bring Your Own Key) model - users provide their own API keys.

- **PHP:** 7.4+ with PSR-4 autoloading (`Softminal\ImageCraft\` namespace)
- **Frontend:** Vue 3 + Tailwind CSS + Vite
- **WordPress:** 5.8+, MySQL 5.7+

## Commands

```bash
# Development server (Vite on port 4000 with HMR)
npm run dev

# Production build
npm run prod

# Lint JavaScript/Vue files
npm run lint

# Lint and auto-fix
npm run lint:fix

# Full validation (lint + production build)
npm run validate

# Create distribution ZIP
./build.sh

# Preview production build
npm run serve
```

## Quality Gates (Automated)

The project uses **Husky + lint-staged** to enforce quality on every commit:

- **Pre-commit hook:** Runs ESLint on staged `.js` and `.vue` files
- **ESLint errors block commits** — warnings are allowed but should be fixed
- **Production build (`npm run prod`)** must succeed before releasing

### When adding new code:
1. Run `npm run lint` to check for issues
2. Run `npm run prod` to verify the build succeeds
3. Run `npm run validate` to do both at once

### PHPStan (optional, requires composer auth)
```bash
composer require --dev phpstan/phpstan szepeviktor/phpstan-wordpress
vendor/bin/phpstan analyse
```

---

## CRITICAL: Live Plugin — Backwards Compatibility

**This plugin is live on WordPress.org with real users on production sites.** Every code change MUST be backwards-compatible and safe for existing installations that update.

### Rules

1. **New settings** must default to pre-existing behavior (opt-in, not opt-out). Existing users should see zero changes after updating unless they explicitly enable a new feature.
2. **Never rename or remove** existing `wp_options` keys, REST API endpoints, response fields, or database columns.
3. **Database schema changes** require a migration in `Install.php` with a version check — handle both fresh installs and upgrades.
4. **New REST API fields** are additive only — add new fields, never rename or remove existing ones.
5. **JSON/serialized settings** must handle empty string, `null`, and missing values gracefully with fallbacks (e.g. `json_decode($val, true) ?: []`).
6. **Repair methods** like `validateStatus()` exist to fix legacy data from older versions — keep them working for all historical states.
7. **Always ask:** "Will a user upgrading from the current version have any problem with this change?"

---

## MANDATORY: Fatal-Error / Site-Down Safety

**This plugin is live on real production sites. No code change may ever be able to cause a fatal error that white-screens a user's admin or public site.** This is the highest-priority constraint — above features, above style.

### Before completing ANY PHP change, Claude MUST verify all of these:

1. **Optional functions are guarded.** Any call to a function from an optional or version-gated dependency (e.g. `wp_ai_client_prompt`, `wp_supports_ai`, WooCommerce `wc_*`, Yoast/Rank Math/AIOSEO/SEOPress, FileBird, Kadence, Spectra) MUST be wrapped in `function_exists()` in the same code path. Calling a missing function is an instant fatal.
2. **Optional/external classes are guarded.** `new \External\Class()` (e.g. `\WordPress\AiClient\…`, WooCommerce classes) MUST be preceded by `class_exists('\…')` or sit inside `try/catch (\Throwable)`. A missing class throws `\Error`, which `catch (\Exception)` does NOT catch.
3. **Catch `\Throwable`, not just `\Exception`, at external-dependency boundaries.** Anything touching an external library, reflection, or type juggling can throw `\Error`/`\TypeError`. Contain throwables at the integration point so they can never reach the caller (pattern: `WP7Provider`).
4. **Broad/unattended hooks must not throw.** Callbacks on `plugins_loaded`, `init`, `current_screen`, `add_attachment`, cron events, REST handlers/permission callbacks, front-end hooks, and **activation/deactivation/uninstall** must be defensive. A fatal here takes down the whole request — worst case, the public site.
5. **Null-safety.** Finders (`::find()`, `get_post()`, `wp_get_attachment_url()`, `ApiSetting::getActive()`) can return `null`/`false`; never dereference without a check.
6. **PHP 7.4 floor.** No 8.0+-only syntax (enums, `readonly`, union/`mixed` types, `str_contains`/`str_starts_with` without polyfill, constructor promotion, named args into plugin code).

### After the change

Run the `/fatal-safety` skill (or its checks) on the diff. **If it finds any BLOCKER, the change is not done.** For anything non-trivial — especially changes touching AI providers, external integrations, hooks, activation, or `uninstall.php` — running `/fatal-safety` is REQUIRED, not optional.

---

## MANDATORY: Pre-Change Impact Analysis

**Before writing ANY code, Claude MUST perform this checklist:**

### Step 1: Identify the change scope
- Which files will be modified?
- Which feature module does this belong to? (See Dependency Map below)

### Step 2: Check the Dependency Map
- What depends on the files being changed?
- What do the changed files depend on?
- Could this change break any other feature?

### Step 3: Backwards compatibility check
- Does this change modify any existing API response shape?
- Does this change rename/remove any wp_options key?
- Does this change alter database schema?
- Does this change modify any existing function signature?
- If YES to any: stop and redesign to be additive-only.

### Step 4: After making changes
- Run `npm run lint` — fix any errors
- Run `npm run prod` — verify build succeeds
- Test the specific feature manually if possible
- Update this CLAUDE.md if the architecture changed

---

## Architecture

### Entry Point Flow
`imagecraft.php` → loads `config/constants.php` → initializes `App.php` singleton on `plugins_loaded`

### PHP Structure (includes/)

**Core Bootstrap:**
- `App.php` - Singleton that loads dependencies in order and initializes Enqueue, Menu, Router, and AutoGenerate
- `Enqueue.php` - Registers and enqueues all CSS/JS assets
- `EnqueueAsset.php` - Vite dev/prod asset resolution helper
- `Menu.php` - WordPress admin menu registration. Shows the ImageCraft menu to admins (`manage_options`) always, and to permitted non-admin roles (`Permission::isAllowed()`) with the `upload_files` capability. Non-permitted roles get no menu.
- `Install.php` - Database table creation and version-gated migrations
- `AutoGenerate.php` - Legacy auto-generate-on-upload (alt/title/Woo). **No longer initialized** — `App::run()` does NOT call `AutoGenerate::init()`. Auto-generate-on-upload is now a **Pro-only** feature owned by the Pro add-on (`Features\Meta`), which calls the free `GeneratorController::generateMetaForImage` core. File kept for backwards-compat references only.
- `Fallback.php` - Image Fallbacks frontend surface (the plugin's ONLY public-frontend code). Opt-in, default off, non-destructive: (1) broken-`<img>` onerror swap via a raw JS asset (`resources/public/js/fallback.js`); (2) missing-featured-image fallback via a `get_post_metadata` (`_thumbnail_id`) filter (primary — makes `has_post_thumbnail()` true) + `post_thumbnail_html` (secondary). Bails on `is_admin()`; hot-path meta filter does cheap key/flag bails first with static-cached settings and a recursion guard. Pro unlock point: `apply_filters('icais_fallback_featured_image_id', $globalId, $postId, $postType)`.
- `AdminNotice.php` - Admin dashboard notices with dismiss functionality
- `DeactivationSurvey.php` - Deactivation feedback modal on the Plugins screen (renders survey, forwards feedback via FeedbackController)

**HTTP Layer:**
- `Http/api.php` - All REST route definitions
- `Http/Routes/Route.php` - Route registry with middleware support
- `Http/Routes/Router.php` - WordPress REST API integration
- `Http/Middleware/RoleGate.php` - Middleware attached to the `generator`, `compressor`, and `renamer` route groups; allows the request only when `Support\Permission::isAllowed()` is true (single role-based permission gate for all action features). Loaded via the `includes/Http/Middleware` entry in `App::$loadOrder`.
- `Http/Controllers/` - GeneratorController, ScannerController, BatchController, SettingsController, StatsController, FileRenamerController, CompressorController, WooCommerceController, FileBirdController, FeedbackController, FallbackController (broken-image scan: time-budgeted, resumable via transient cursor; `scanBrokenCore()` is the public reusable core the Pro scheduled-scan cron calls)

**Models (`Models/`):** Eloquent-like ORM with query builder, casting, and WP object cache integration.
- `ApiSetting` - Encrypted API keys per user/provider
- `History` - Generation tracking with status (pending/accepted/rejected)
- `Stats` - Daily usage statistics
- `BatchJob` - Batch processing jobs
- `Compression` - Image compression history (original/compressed size, savings, backup path, status)

**Support (`Support/`):**
- `AltTextHelper.php` - Shared alt text utilities used by Scanner, Stats, WooCommerce controllers
- `Collection.php` - Collection class used by Model base class for query results
- `Permission.php` - Single role-based access gate for ALL ImageCraft action features (alt text, title, file rename, compression). `isAllowed($userId = null)`: admins (`manage_options`) always allowed; empty `icais_allowed_roles` = no restriction (legacy behavior); otherwise the user's roles must intersect the allow-list. Used by `RoleGate` middleware, `AutoGenerate`, and `Enqueue` (media-button/Gutenberg `canUse` flag).

**Services (`Services/`):**
- `AI/AIFactory.php` - Factory pattern for AI providers
- `AI/BaseAIProvider.php` - Abstract class with image handling, prompts by tone
- `AI/AnthropicProvider.php`, `OpenAIProvider.php`, `GeminiProvider.php` - Active provider implementations
- `AI/ChatCompletionsProvider.php` - Shared base for OpenAI-compatible Chat Completions API
- `AI/MistralProvider.php`, `AI/OpenRouterProvider.php` - Planned providers (commented out in AIFactory)
- `Database.php` - Database setup, activation/deactivation hooks
- `Encryption.php` - API key encryption using WordPress salts
- `Settings.php` - Plugin settings management
- `FileRenamer.php` - SEO-friendly image file renaming
- `ImageCompressor.php` - Local, **quality-preserving** image compression. Direct **Imagick** pass (strip EXIF/profiles + progressive JPEG, **no chroma subsampling**, visually-lossless quality) with a `wp_get_image_editor` (GD) fallback; optional **longest-edge downscale**; writes to a temp file and only commits if smaller (**never-enlarge / already-optimal guard**); same path/format → URL unchanged; backs up originals to `uploads/icais-originals/` and supports restore. Defaults: quality **85** (`DEFAULT_QUALITY`), downscale to **2048px** longest edge (`FREE_MAX_DIM`). The engine is Pro-agnostic — it reads quality/resize via `apply_filters('icais_compression_quality'|'icais_compression_max_dim', …)` which the Pro add-on overrides when licensed. Re-compress preserves the true-original backup.
- `StatsTracker.php` - Centralised generation history and stats recording
- `SeoIntegration.php` - Yoast/Rank Math focus keyphrase integration
- `UsageLimiter.php` - Daily limits (used by Generator, Batch, WooCommerce, AutoGenerate). Two separate counters: generation (50/day free, key `icais_daily_usage_<id>`) and compression (20/day free, key `icais_daily_compress_<id>`). Pro bypasses both via `ICAIS_PRO`.
- `TranslationStrings.php` - i18n strings passed to frontend via wp_localize_script
- `WooCommerce.php` - WooCommerce product/gallery integration service

### Frontend Structure (resources/admin/)

**Entry:** `main.js` → Vue 3 app mounted to `#imagecraft-app`

**Router (`router.js`):** Hash history mode with routes for Dashboard, Scanner, WooCommerce, Batch, Settings, Help, and SetupWizard. The `beforeEach` guard redirects to `/wizard` only when `wizard_completed` is false — an API key is **no longer required** to enter the SPA (the plugin can be used purely for compression). The wizard can be **skipped without a key** (`skipWizard()` → `/settings/wizard/complete`); AI features then show an inline "Add API key" prompt at point of use (`window.icais.hasAiKey`).

**API Client (`services/Api.js`):** REST client using `window.imageCraft` config for baseURL and nonce.

**Key Views:** Dashboard.vue, Scanner.vue, Compression.vue, Batch.vue, Settings.vue, SetupWizard.vue, WooCommerce.vue, Help.vue

**Settings Sub-routes:** GeneralSettings, ApiSettings, AltTextSettings, TitleSettings, MetaSettings (route `/settings/meta` — combined "Generate All"), RenameSettings, CompressionSettings, FallbackSettings (route `/settings/fallback` — Image Fallbacks; uses shared `ImagePick.vue` wp.media picker), WooCommerceSettings, SeoSettings, IntegrationSettings, PermissionsSettings (route `/settings/permissions` — single role allow-list for all features)

**Shared UI (`@ui/`):** Notification.vue (global notification plugin), Pagination.vue

**Plugins (`plugins/`):** notification.js (Vue plugin exposing `$notification` globally)

---

## Dependency Map (Feature → Files)

Use this map to understand what a change will impact. **Always check downstream dependents before modifying a file.**

### Core (changes here affect EVERYTHING)
```
imagecraft.php → config/constants.php → includes/App.php
includes/Models/Model.php → ALL models
includes/Http/Routes/Route.php → ALL routes
includes/Http/Controllers/Controller.php → ALL controllers
includes/Services/Settings.php → ALL controllers, ALL views
resources/admin/services/Api.js → ALL Vue views
resources/admin/router.js → ALL Vue views
```

### AI Generation (the main feature)
```
PHP:
  Services/AI/BaseAIProvider.php ← AnthropicProvider, OpenAIProvider, GeminiProvider
  Services/AI/ChatCompletionsProvider.php ← OpenAIProvider, GeminiProvider
  Services/AI/AIFactory.php → creates provider instances
  Http/Controllers/GeneratorController.php → uses AIFactory, History, StatsTracker
  Http/Controllers/BatchController.php → uses AIFactory, BatchJob, History, StatsTracker
  Services/StatsTracker.php → History model, Stats model

Frontend:
  views/Scanner.vue → Api.js (generator/generate, generator/accept, generator/reject)
  views/Batch.vue → Api.js (batch/start, batch/status, batch/results, batch/process)
  views/Dashboard.vue → Api.js (stats/dashboard, stats/audit, compressor/stats) — dual-focus dashboard (Alt Text + Compression panels)
```

### Settings System
```
PHP:
  Services/Settings.php → wp_options (icais_* keys)
  Services/Encryption.php → ApiSetting model
  Http/Controllers/SettingsController.php → Settings, Encryption, ApiSetting

Frontend:
  views/Settings.vue → parent layout for all settings sub-routes
  components/Settings/GeneralSettings.vue → Api.js (settings GET/POST)
  components/Settings/ApiSettings.vue → Api.js (settings/api-key, settings/test-api)
  components/Settings/AltTextSettings.vue → Api.js (settings POST)
  components/Settings/TitleSettings.vue → Api.js (settings POST)
  components/Settings/RenameSettings.vue → Api.js (settings POST)
  components/Settings/WooCommerceSettings.vue → Api.js (settings POST)
  components/Settings/SeoSettings.vue → Api.js (settings POST)
  components/Settings/IntegrationSettings.vue → parent for Woo + SEO settings
```

### WooCommerce Integration
```
PHP:
  Services/WooCommerce.php → WooCommerce plugin dependency
  Http/Controllers/WooCommerceController.php → WooCommerce service, AIFactory, StatsTracker

Frontend:
  views/WooCommerce.vue → Api.js (woocommerce/* endpoints)
  components/Settings/WooCommerceSettings.vue → Api.js (settings POST)
```

### File Renamer
```
PHP:
  Services/FileRenamer.php → WordPress media functions
  Http/Controllers/FileRenamerController.php → FileRenamer service

Frontend:
  Scanner.vue (rename buttons) → Api.js (renamer/rename, renamer/rename-bulk)
```

### Image Compression
```
PHP:
  Services/ImageCompressor.php → wp_get_image_editor, WP_Filesystem (backup/restore), Compression model
  Http/Controllers/CompressorController.php → ImageCompressor, UsageLimiter (canCompress), Compression model
  Models/Compression.php → icais_compressions table
  Services/UsageLimiter.php → canCompress/recordCompressUsage (separate 20/day counter; ICAIS_PRO bypass)
  Http/Controllers/BatchController.php → type='compress' branch calls ImageCompressor (bulk)
  Install.php → icais_compressions table (db_version 1.1.0 migration)

Pro unlock points (free exposes hooks; the Pro add-on overrides them ONLY with a valid license):
  apply_filters('icais_compression_quality', 85)   → Pro returns the user's quality setting
  apply_filters('icais_compression_max_dim', 2048)  → Pro returns the user's resize cap (0 = off)
  ICAIS_PRO constant                                → UsageLimiter::isPro() removes the 20/day cap
  add_action('add_attachment')/'icais_auto_compress' → registered by the PRO plugin (auto-compress
    lives in imagecraft-...-pro/includes/Features/Compression.php, NOT in the free plugin)

Frontend:
  views/Compression.vue → Api.js (compressor/scan, compressor/compress, compressor/restore, compressor/stats; batch/start type:'compress')
  components/Settings/CompressionSettings.vue → settingsContext (compression settings; Pro-locked controls)
  resources/admin/js/media-button.js → "Compress" button in the WP media modal (shown when
    icais_compression_enabled; calls compressor/compress). Localized via Enqueue.php (icaisMediaButton)
  Pro gating: window.icais.isPro hides upsell / unlocks quality, max_width, WebP(future), auto-compress

Free vs Pro:
  Free  → manual single + bulk compress, fixed quality 85 (visually lossless) + fixed 2048px longest-edge downscale, 20/day cap, backup+restore
  Pro   → unlimited, adjustable quality, adjustable/disable downscale, auto-compress on upload
  Split: the engine + manual UI + free caps live in the FREE plugin. The Pro add-on
  (imagecraft-...-pro) owns the value-unlocks (via the filters above) and auto-compress, and
  registers them ONLY when the license is valid. So the premium *behavior* is not in WP.org source.
  The 20/day cap bypass still rides the ICAIS_PRO constant (consistent with alt-text gating).
```

### Image Fallbacks (broken images + missing featured images) — first public-frontend surface
```
PHP:
  includes/Fallback.php → Settings; hooks wp_enqueue_scripts, get_post_metadata (_thumbnail_id,
    PRIMARY — makes has_post_thumbnail() true; is_admin() bail + recursion guard),
    post_thumbnail_html (secondary). Frontend-only (init() bails in admin). Non-destructive.
  Http/Controllers/FallbackController.php → broken-image scan. scanBrokenCore() = public
    reusable core (Pro cron calls it); REST scanBroken() wraps it. Time-budgeted (~10s/request),
    resumes via cursor in transient icais_broken_scan (partial:true response → caller re-calls).
  resources/public/js/fallback.js → raw (non-Vite) frontend JS: capture-phase error listener
    (img error doesn't bubble) + initial naturalWidth===0 sweep; data-icais-fallback loop guard.
    Enqueued with ICAIS_URL + wp_localize_script('icaisFallback'). build.sh copies resources/public.
  App.php → 'Fallback.php' in includes load order; guarded instantiation + $this->fallback->init().
  Enqueue.php → wp_enqueue_media() on toplevel_page_icais (for the settings image pickers).

Pro unlock points (free exposes hooks; the Pro add-on overrides ONLY with a valid license —
identical split to Compression):
  apply_filters('icais_fallback_featured_image_id', $globalId, $postId, $postType)
    → Pro returns a per-post-type image from icais_fallback_featured_map
  FallbackController::scanBrokenCore()          → called by the Pro icais_fallback_scan cron
  icais_fallback_scan cron + email alert        → registered by the PRO plugin only
    (Features/Fallbacks.php in imagecraft-...-pro; free registers NO cron)

Frontend:
  components/Settings/FallbackSettings.vue → /settings/fallback (master + broken + featured
    toggles, ImagePick pickers, post-type allow-list; Pro-locked per-type map + schedule/email)
  components/Settings/ImagePick.vue → shared wp.media image picker (preview + choose/remove)
  views/Dashboard.vue → loadBrokenScan() loops GET /fallback/scan-broken while partial;
    "Broken images" Needs-attention tile (links /settings/fallback); ≤10-point overallScore penalty
  Discovery UX: (1) Dashboard promo card (admins, only while icais_fallback_enabled is off,
    dismissible via localStorage key icais_fallback_promo_dismissed) → /settings/fallback;
    (2) SetupWizard.vue step 4 "Image Fallbacks" (optional, one ImagePick used for BOTH broken +
    featured; wizard/complete fires BEFORE step 4 so abandoning loses nothing; step 5 = success
    screen, previously dead code); (3) the Broken-images tile itself (scan runs even when the
    module is off — problem-driven discovery)

Settings (all opt-in, default off): icais_fallback_enabled, icais_fallback_broken_enabled,
  icais_fallback_broken_image_id, icais_fallback_featured_enabled, icais_fallback_featured_image_id,
  icais_fallback_featured_post_types ('[]' = all public). Pro-effect (inert in free):
  icais_fallback_featured_map ('{}'), icais_fallback_scan_schedule ('0'|'daily'|'weekly'),
  icais_fallback_scan_email ('1')

Free vs Pro:
  Free → one global fallback image (broken + featured), post-type allow-list, manual scan + tile
  Pro  → per-post-type featured fallbacks, scheduled scan (daily/weekly) + email on NEW broken images
```

### SEO Integration
```
PHP:
  Services/SeoIntegration.php → Yoast/Rank Math plugin dependency
  Used by: BaseAIProvider (for focus keyphrase in prompts)

Frontend:
  components/Settings/SeoSettings.vue → Api.js (settings POST)
```

### Auto-Generate (on upload) — PRO-ONLY
```
The FREE plugin does NOT auto-generate on upload (AutoGenerate::init() is not called).
This is now a Pro feature, mirroring auto-compress:
  PRO: imagecraft-...-pro/includes/Features/Meta.php registers add_attachment (license-gated)
       → schedules icais_auto_generate_meta cron → calls the FREE reusable core
       GeneratorController::generateMetaForImage (alt + title + caption per icais_meta_include_*).
  Pro autoEnabled() also honors the legacy toggles (icais_auto_generate / icais_title_auto_generate /
       icais_woo_auto_generate) so existing Pro users keep auto without reconfiguring.
  Settings UI: the single Pro-locked "Auto-generate on upload" toggle lives in MetaSettings.vue
       (+ post-type exclusion). The old per-page auto toggles (Alt Text / Image Title / WooCommerce)
       were REMOVED. Legacy setting keys remain in defaults (inert in free) for backwards-compat.
```

### Combined Meta — "Generate All" (alt + title + caption, one AI call)
```
PHP:
  Services/AI/BaseAIProvider.php → setMetaMode()/getMetaPrompt() (strict JSON {alt_text,title,caption})
  Http/Controllers/GeneratorController.php → generateMetaForImage() (core, public — also called by
    BatchController and the Pro auto-meta add-on), generateMeta()/acceptMeta() (REST),
    parseMetaJson()/capText()/persistMeta() (helpers)
  Http/Controllers/BatchController.php → type='meta' branch (bulk; forceAccept)
  Saves: alt → _wp_attachment_image_alt; title → _icais_generated_title + post_title;
         caption → _icais_generated_caption (tracking) + post_excerpt (source of truth)
  Usage: counts as ONE generation (UsageLimiter::recordUsage once)

Frontend:
  views/Scanner.vue → bulk **field picker** (admin): [Alt text][Title][Description] checkboxes + one
    "Generate (N)" button → batch/start type:'meta' with fields:[...] (the picked fields override the
    icais_meta_include_* settings for that run). Checkbox defaults come from window.icais.metaIncludeAlt/
    Title/Caption. This REPLACED the old separate Generate Alt/Title/All buttons. Per-batch fields flow:
    BatchController stores them in transient icais_batch_fields_<id>; generateMetaForImage($fields)
    + BaseAIProvider::setMetaFields() keep the AI prompt and the saved fields in sync. The 'alt'/'title'
    batch types still exist server-side but the Scanner no longer triggers them.
    Caption has NO standalone generator — it lives only inside the combined meta path.
  components/Settings/MetaSettings.vue → /settings/meta (toggles + caption tone/length; Pro-locked auto-on-upload)
  resources/admin/js/media-button.js → per-image **field picker** in WP media modal (Alt/Title/Description
    checkboxes + one Generate button, mirroring the bulk picker) → generator/generate-meta with fields:[...].
    Always shown when canUse; checkbox defaults from icaisMediaButton.metaIncludeAlt/Title/Caption.
    Compress + Rename remain separate buttons. (icais_meta_enabled is now vestigial — no longer gates any UI;
    key kept in defaults for backwards-compat.)

Settings (all additive, opt-in): icais_meta_enabled (master, off), icais_meta_include_alt/title/caption (on),
  icais_meta_auto_generate (Pro-gated effect), icais_caption_max_length (150),
  icais_caption_default_tone, icais_caption_skip_existing

Pro split: auto-generate-on-upload is PRO-ONLY (mirrors auto-compress). The FREE plugin exposes the
  reusable generateMetaForImage() core + a Pro-locked UI toggle but registers NO add_attachment hook for
  meta; the Pro add-on registers a guarded add_attachment handler that calls the free core. ICAIS_PRO gates it.
```

### Admin Notices
```
PHP:
  includes/AdminNotice.php → Settings
  Http route: notice/dismiss

Frontend:
  Rendered server-side in WP admin
```

### Deactivation Survey (Plugins screen)
```
PHP:
  includes/DeactivationSurvey.php → renders modal on plugins.php (current_screen hook)
    → enqueues resources/admin/css/deactivation-survey.css (hand-written, not Vite)
    → inline vanilla JS intercepts the ImageCraft "Deactivate" link
  Http/Controllers/FeedbackController.php → forwards feedback via wp_remote_post
  Http route: feedback/deactivation (capability activate_plugins)
  Remote: ICAIS_FEEDBACK_ENDPOINT constant / icais_feedback_endpoint filter
          (default https://admin.softminal.com/wp-json/softminal/v1/feedback)
          Payload: plugin_slug, action, reason, message, site_url, plugin_version, wp_version, php_version
          Reason keys: temporary, not_working, better_found, too_complex, no_longer, other

Frontend:
  Rendered server-side on plugins.php only — NOT part of the Vue SPA.
  Feedback sent only on explicit "Submit & Deactivate"; "Skip" sends nothing.
```

---

## REST API

**Namespace:** `/wp-json/icais/v1`

| Endpoint | Controller | Capability |
|----------|------------|------------|
| `GET /settings` | SettingsController::index | manage_options |
| `POST /settings` | SettingsController::update | manage_options |
| `POST /settings/api-key` | SettingsController::saveApiKey | manage_options |
| `POST /settings/test-api` | SettingsController::testApi | manage_options |
| `POST /settings/wizard/complete` | SettingsController::completeWizard | manage_options |
| `GET /scanner/scan` | ScannerController::scan | upload_files |
| `GET /scanner/select-all` | ScannerController::selectAll | upload_files |
| `POST /generator/generate` | GeneratorController::generate | upload_files |
| `POST /generator/generate-meta` | GeneratorController::generateMeta | upload_files |
| `POST /generator/accept` | GeneratorController::accept | upload_files |
| `POST /generator/accept-bulk` | GeneratorController::acceptBulk | upload_files |
| `POST /generator/accept-meta` | GeneratorController::acceptMeta | upload_files |
| `POST /generator/update-alt` | GeneratorController::updateAlt | upload_files |
| `POST /generator/reject` | GeneratorController::reject | upload_files |
| (the `generator`, `compressor`, and `renamer` groups also run `Http\Middleware\RoleGate` after the capability check — see `icais_allowed_roles` below) | | |
| `POST /batch/start` | BatchController::start | manage_options |
| `GET /batch/active` | BatchController::active | manage_options |
| `POST /batch/resume` | BatchController::resume | manage_options |
| `GET /batch/status` | BatchController::status | manage_options |
| `GET /batch/results` | BatchController::results | manage_options |
| `GET /batch/failures` | BatchController::failures | manage_options |
| `POST /batch/retry-failed` | BatchController::retryFailed | manage_options |
| `POST /batch/process` | BatchController::process | manage_options |
| `POST /batch/cancel` | BatchController::cancel | manage_options |
| (batch `type`) | `alt` (default) · `title` · `compress` · `meta` (alt+title+caption combined) — set on `/batch/start`, branched in `process()` | |
| `GET /stats/dashboard` | StatsController::dashboard | upload_files |
| `GET /stats/audit` | StatsController::audit | upload_files |
| `POST /renamer/rename` | FileRenamerController::rename | upload_files |
| `POST /renamer/rename-bulk` | FileRenamerController::renameBulk | upload_files |
| `GET /renamer/preview` | FileRenamerController::preview | upload_files |
| `GET /compressor/scan` | CompressorController::scan | upload_files |
| `POST /compressor/compress` | CompressorController::compress | upload_files |
| `POST /compressor/restore` | CompressorController::restore | upload_files |
| `GET /compressor/preview` | CompressorController::preview | upload_files |
| `GET /compressor/stats` | CompressorController::stats | upload_files |
| `GET /fallback/scan-broken` | FallbackController::scanBroken | upload_files |
| (`?refresh=1` busts the `icais_broken_scan` transient; response may be `partial: true` — re-call to resume the time-budgeted scan) | | |
| `POST /notice/dismiss` | AdminNotice::dismiss | edit_posts |
| `POST /feedback/deactivation` | FeedbackController::deactivation | activate_plugins |
| `GET /woocommerce/products` | WooCommerceController::getProducts | manage_woocommerce |
| `GET /woocommerce/categories` | WooCommerceController::getCategories | manage_woocommerce |
| `GET /woocommerce/stats` | WooCommerceController::getStats | manage_woocommerce |
| `POST /woocommerce/generate` | WooCommerceController::generate | manage_woocommerce |
| `POST /woocommerce/generate-gallery` | WooCommerceController::generateGallery | manage_woocommerce |
| `GET /woocommerce/gallery` | WooCommerceController::getGallery | manage_woocommerce |

## Database Tables

All tables use prefix `{$wpdb->prefix}icais_`:
- `api_settings` - Encrypted API keys (user_id, provider, encrypted_key, iv)
- `history` - Generation history (attachment_id, original/generated alt, status, tokens, cost)
- `stats` - Daily stats per user (processed, accepted, rejected, cost)
- `batch_jobs` - Batch job tracking (total, processed, failed, status)
- `compressions` - Compression history (attachment_id, user_id, original/compressed size, saved bytes/percent, method, backup_path, status) — added in db_version 1.1.0 via `Install::maybeUpgrade()`

**DB schema version:** `Install::DB_VERSION` (currently `1.1.0`), stored in `wp_options['icais_db_version']`. `Install::maybeUpgrade()` runs on `plugins_loaded` (via `App::run()`) and applies idempotent, version-gated migrations for existing installs (the activation hook does NOT fire on plugin update). Wrapped in `try/catch (\Throwable)` so it can never white-screen a site.

## Key Constants (config/constants.php)

- `ICAIS_DEV_MODE` - true when `.dev` file exists in plugin root (enables Vite dev server)
- `ICAIS_API_NAMESPACE` - `icais/v1`
- `ICAIS_TABLE_PREFIX` - `icais_`
- `ICAIS_VERSION` - Plugin version string

## AI Provider Details

All providers use `BaseAIProvider` for common functionality:
- Image fetching (local filesystem or remote URL)
- Base64 encoding with MIME type detection (jpg, png, gif, webp)
- Tone-based prompts: professional, casual, technical, seo, woo_seo, woo_descriptive, woo_lifestyle, custom
- Configurable max alt text length (default 150 characters)
- Multi-model support with per-provider model selection

**Default Models:**
- Anthropic: `claude-haiku-4-5`
- OpenAI: `gpt-4o-mini`
- Gemini: `gemini-2.5-flash`

## Development Notes

- Asset enqueuing auto-detects dev/prod mode via `ICAIS_DEV_MODE` constant
- Dev mode loads from `http://localhost:4000`, prod from `assets/manifest.json`
- Vue router uses wizard completion check stored in settings (`wizard_completed` only; the API-key requirement was dropped so compression-only users aren't blocked). `window.icais.hasAiKey` (set in `Enqueue.php`, guarded) drives the inline "Add API key" prompt shown on Dashboard/Scanner when no provider is configured.
- Models use WP object cache with automatic version busting on updates
- API keys are AES-256-CBC encrypted using WordPress AUTH_KEY and SECURE_AUTH_KEY
- Settings keys are stored in wp_options with `icais_` prefix prepended (e.g. `icais_icais_max_length`)
- **Role-based permission** (`icais_allowed_roles`): a SINGLE JSON array of role slugs allowed to use ImageCraft. Governs ALL action features — alt text, image titles, file rename, and image compression. **Empty = no restriction** (legacy behavior; backwards-compatible default). Administrators always allowed. Enforced server-side by `Http\Middleware\RoleGate` on the `generator`, `compressor`, and `renamer` route groups, plus an `AutoGenerate::onAttachmentAdded` guard; reflected client-side by the `canUse` flag passed to `icaisMediaButton`/`icaisGutenberg` (hides ALL media-modal buttons — Generate/Title/Rename/Compress — when false). UI lives in the dedicated **Permissions** settings page (`components/Settings/PermissionsSettings.vue`, route `/settings/permissions`); selectable roles come from `SettingsController::index()` `available_roles` (excludes `administrator`).

**Non-admin SPA access:** permitted roles also get the ImageCraft admin menu + SPA, limited to **Dashboard + Image SEO (Scanner) + Compress**. `Menu.php` registers the menu for them with the `upload_files` cap (admins keep `manage_options`). `Enqueue.php` passes `isAdmin`, `wizardCompleted`, and `canUse` in `window.icais`. The frontend hides admin-only surfaces for non-admins: `router.js` `beforeEach` blocks `/settings`, `/batch`, `/woocommerce`, `/wizard` (and skips the admin-only `/settings` fetch); `Navbar.vue` shows only the three base tabs; `Scanner.vue`/`Compression.vue` hide bulk `batch/*` actions (admin-only) leaving per-image generate/compress; `Dashboard.vue` skips the settings/wizard check and admin-only CTAs. Batch routes stay `manage_options` (admin-only). Pre-existing limitation: `GeneratorController::generate` enforces attachment ownership, so a non-admin can only generate for their own uploads.

---

## WordPress Security Rules (MANDATORY)

Every code change MUST follow WordPress security best practices. Violations can get the plugin **removed from WordPress.org**.

### Input Validation & Sanitization
- **ALL user input** must be sanitized: `sanitize_text_field()`, `sanitize_email()`, `absint()`, `wp_kses()`, etc.
- **ALL output** must be escaped: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`, etc.
- **Never** use `$_GET`, `$_POST`, `$_REQUEST` directly — use `sanitize_*()` wrappers.
- **SQL queries** must use `$wpdb->prepare()` for ALL dynamic values. Never concatenate user input into SQL.

### Nonce & Capability Checks
- All REST endpoints already have capability checks via `Route::capability()`. Never create an endpoint without one.
- WordPress REST API handles nonce verification via `X-WP-Nonce` header (set by `Api.js`). Don't bypass this.
- For any admin-ajax or non-REST handler, always verify nonce with `wp_verify_nonce()` or `check_ajax_referer()`.

### File Operations
- Use `WP_Filesystem` API for file operations when possible (except during autoloading/bootstrap).
- Validate file types before processing — never trust user-uploaded file extensions.
- The `FileRenamer` service moves files on disk — ensure path traversal is impossible (no `../` in filenames).

### Data Storage Security
- API keys are AES-256-CBC encrypted via `Encryption.php`. Never store keys in plaintext.
- Never log API keys, tokens, or encrypted data to debug.log.
- Never expose API keys in REST API responses (the settings endpoint returns `has_key: bool`, not the key itself).

### Third-Party API Calls
- All external API calls (Anthropic, OpenAI, Gemini) must use `wp_remote_post()`/`wp_remote_get()` — never use `file_get_contents()` or raw `curl`.
- Validate and sanitize API responses before using them.
- Handle API failures gracefully — never expose raw API error messages to non-admin users.

### WordPress.org Review Checklist
Before any release, verify:
- [ ] No direct file operations without WP_Filesystem (except bootstrap)
- [ ] No direct database queries without `$wpdb->prepare()`
- [ ] No unescaped output (`echo $var` → `echo esc_html($var)`)
- [ ] No unsanitized input
- [ ] All endpoints have capability checks
- [ ] No hardcoded URLs or paths
- [ ] No `eval()`, `exec()`, `system()`, or similar
- [ ] No inclusion of minified JS without source (Vite handles this correctly)

---

## Coding Conventions (MUST Follow)

Claude MUST follow these conventions exactly. They are derived from the existing codebase — consistency is non-negotiable.

### Naming Conventions

| Context | Convention | Examples |
|---------|-----------|----------|
| PHP classes | PascalCase | `GeneratorController`, `BatchJob`, `StatsTracker` |
| PHP methods | camelCase | `generateAltText()`, `saveApiKey()`, `getForAttachment()` |
| PHP variables | camelCase | `$attachmentId`, `$altText`, `$currentUserId` |
| PHP constants | UPPER_SNAKE_CASE | `ICAIS_TABLE_PREFIX`, `ICAIS_VERSION` |
| PHP namespaces | `Softminal\ImageCraft\{Dir}` | `Softminal\ImageCraft\Http\Controllers` |
| Vue view files | PascalCase.vue | `Dashboard.vue`, `Scanner.vue`, `WooCommerce.vue` |
| Vue component files | PascalCase.vue | `GeneralSettings.vue`, `ImageCard.vue` |
| JS service files | PascalCase.js | `Api.js` |
| JS utility/config files | camelCase.js | `router.js`, `main.js`, `i18n.js` |
| CSS/SCSS files | camelCase | `index.scss` |
| REST route prefixes | lowercase | `scanner`, `generator`, `batch`, `woocommerce` |
| wp_options keys | snake_case with `icais_` prefix | `icais_icais_default_tone` |
| DB table names | snake_case with `icais_` prefix | `icais_history`, `icais_batch_jobs` |

### PHP Patterns

**Controller methods** — always return `$this->response()`:
```php
// Success
return $this->response([
    'success' => true,
    'message' => __('Alt text saved successfully', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
    'data'    => $result,
]);

// Error with HTTP status
return $this->response([
    'success' => false,
    'message' => __('Attachment ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
], 400);
```

**Parameter sanitization** — at the top of every controller method:
```php
$params = $request->get_json_params();
$attachmentId = (int) ($params['attachment_id'] ?? 0);
$tone = sanitize_text_field($params['tone'] ?? Settings::get('icais_default_tone', 'professional'));
```

**Model queries** — Eloquent-like chainable builder:
```php
$records = History::where('attachment_id', $attachmentId)
    ->orderBy('created_at', 'DESC')
    ->get();

$single = History::find($id);

$bulk = History::where('id', 'IN', $ids)->get();
```

**Settings access** — static `Settings::get()` with defaults:
```php
$value = Settings::get('icais_default_tone', 'professional');
```

**Error handling** — try-catch with WP_DEBUG logging:
```php
try {
    $result = $service->doWork();
    return $this->response($result);
} catch (\Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('ImageCraft: ' . $e->getMessage());
    }
    return $this->response([
        'success' => false,
        'message' => __('Something went wrong', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
    ], 500);
}
```

**i18n** — all user-facing strings use text domain:
```php
__('Message here', 'imagecraft-ai-alt-text-file-renamer-image-seo')
```

### Vue/Frontend Patterns

**Script setup** with `inject()` for shared context:
```vue
<script setup>
import { ref, inject } from 'vue'
import Api from '../../services/Api'
import { $t } from '../../utils/i18n'

const { settings } = inject('settingsContext')
const loading = ref(false)
</script>
```

**API calls** — always through `Api` service, never raw fetch:
```javascript
const data = await Api.get('/settings')
const result = await Api.post('/generator/generate', { attachment_id: id, tone: 'seo' })
```

**Translations** — use `$t()` helper in templates:
```vue
<h1>{{ $t('Media Library Scanner') }}</h1>
```

**State management** — NO Vuex/Pinia. Uses:
1. Component-level `ref()` for local state
2. `inject('settingsContext')` for shared settings in child components
3. Router-level 5-second cache for settings (see `router.js`)
4. Call `clearSettingsCache()` after updating settings

**Tailwind classes** — inline in template, conditional with array syntax:
```vue
:class="[
    'px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
    isActive ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
]"
```

### REST API Response Shape

**Every response MUST include `success` boolean:**
```json
// Success
{ "success": true, "message": "...", ...additional_fields }

// Error
{ "success": false, "message": "..." }
```

**HTTP status codes used:** 200 (success), 400 (validation), 401 (auth), 403 (forbidden), 404 (not found), 429 (rate limit), 500 (server error)

### Adding New Code — Where Things Go

| What you're adding | Where it goes |
|-------------------|---------------|
| New REST endpoint | Define route in `Http/api.php`, create/use controller in `Http/Controllers/` |
| New model | `Models/` directory, extend `Model` base class |
| New service | `Services/` directory, add to `App.php` `$loadOrder` if needed |
| New AI provider | `Services/AI/`, extend `BaseAIProvider`, register in `AIFactory` |
| New Vue page/view | `resources/admin/views/`, add route in `router.js` |
| New Vue component | `resources/admin/components/{Feature}/` |
| New settings sub-page | `resources/admin/components/Settings/`, add child route in `router.js` |
| New wp_options key | Use via `Settings::get()`/`Settings::set()`, add default to `Settings::$defaults` |
| New DB table | Add to `Install.php` with version-gated migration |

---

## Common Pitfalls (Read Before Coding)

### PHP Pitfalls
1. **Settings double-prefix:** Options are stored as `icais_icais_*` (the prefix is applied twice). Don't "fix" this — it's intentional legacy behavior.
2. **Model caching:** Models use WP object cache. After updating a record, the cache must be busted or you'll read stale data.
3. **Encryption dependency:** `Encryption.php` depends on WordPress `AUTH_KEY` and `SECURE_AUTH_KEY` constants. Never change the encryption mechanism.
4. **Load order matters:** `App.php` loads files in a specific order. If you add a new class, place it correctly in the `$loadOrder` array based on its dependencies.
5. **REST route registration:** All routes are defined in `Http/api.php`. When adding a new route, add it there — don't create separate route files.

### Frontend Pitfalls
1. **API base URL:** All API calls go through `services/Api.js` which reads from `window.imageCraft`. Never hardcode API URLs.
2. **Router guard:** The `beforeEach` guard in `router.js` redirects to wizard if setup is incomplete. New routes must work with this guard.
3. **Settings cache:** `router.js` caches settings for 5 seconds. After saving settings, call `clearSettingsCache()`.
4. **Tailwind scoping:** Tailwind is configured to scan `resources/**/*.{vue,js,ts}` only. CSS classes used outside this scope won't be generated.

### WooCommerce Pitfalls
1. **Capability check:** WooCommerce routes use `manage_woocommerce` capability, not `manage_options`. Don't mix them.
2. **Plugin dependency:** WooCommerce features must gracefully handle WooCommerce not being installed/active.

---

## AI Agent Commands (Slash Commands)

These custom slash commands are available in Claude Code for automated testing, monitoring, and debugging. Your developers should use these regularly.

| Command | What it does | When to use |
|---------|-------------|-------------|
| `/fatal-safety` | Verifies a change can never fatal/white-screen a user's site — unguarded optional functions/classes, `catch(\Exception)` that should be `\Throwable`, unsafe hooks, activation/uninstall, PHP 7.4 floor | After ANY PHP change — REQUIRED for AI providers, integrations, hooks, activation, uninstall |
| `/test` | Full test suite — PHP syntax, ESLint, build, class structure, route integrity, security scan | After any code change, before committing |
| `/security-audit` | Deep WordPress security audit — sanitization, escaping, SQL injection, auth, XSS | Before every release, after security-sensitive changes |
| `/impact [file or feature]` | Traces all dependencies and dependents of a file/feature, checks what could break | Before modifying any file — especially core files |
| `/review` | Reviews uncommitted/recent changes for quality, security, compatibility, conventions | Before committing — acts as automated code reviewer |
| `/monitor` | Full codebase health check — dead code, convention violations, CLAUDE.md drift, dependency health | Weekly or after major changes |
| `/pre-release` | Complete release gate — version sync, all tests, security, WP.org compliance, build, changelog | Before releasing to WordPress.org |
| `/debug [description]` | Investigates a bug — traces code path, identifies root cause, proposes fix | When something is broken |

### Developer Workflow

```
1. Before coding:    /impact Scanner.vue
2. Write code
3. After coding:     /fatal-safety   (ensure no change can take a user's site down)
4. After coding:     /review
5. Before commit:    /test
5. Before release:   /pre-release
6. Bug reported:     /debug "batch processing stops at 50%"
7. Weekly checkup:   /monitor
```

---

## For New Developers Using Claude Code

When you ask Claude Code to work on this project, include these in your prompt for best results:

- **"Check the dependency map before making changes"** — ensures Claude analyzes impact
- **"Run npm run lint after changes"** — catches JS issues
- **"Run npm run prod to verify the build"** — catches build breaks
- **"Make sure this is backwards compatible"** — triggers the compatibility checklist
- **"Update CLAUDE.md if you changed the architecture"** — keeps docs current

### Example prompts for safe development:
```
"Add a new setting for X. Make it opt-in with default off. Check the dependency map,
run lint and build after."

"Fix the bug where Y happens. Check what depends on the affected files before changing them."

"Add a new REST endpoint for Z. Add it to api.php, update the REST API table in CLAUDE.md,
and make sure the response shape doesn't break existing endpoints."
```
