# PROJECT STATE — ImageCraft

> **Living document.** This file is the at-a-glance source of truth for the
> project's features, codebase layout, and runtime flows. Claude MUST keep it
> current — see the maintenance rule at the bottom and in `CLAUDE.md`. If
> something here disagrees with the code, the code wins and this file must be
> fixed.

_Last updated: 2026-06-15_

---

## 1. Overview

**ImageCraft** is a live WordPress.org plugin that generates AI-powered alt text
(and titles, SEO-friendly file renames, WooCommerce image text) for media images.
It follows a **BYOK (Bring Your Own Key)** model, and also supports the
**WordPress Native AI Connector** (WP7 AI Client) when present.

- **PHP:** 7.4+, PSR-4 (`Softminal\ImageCraft\`)
- **Frontend:** Vue 3 + Tailwind CSS + Vite 8 (Rolldown)
- **WordPress:** 5.8+, MySQL 5.7+
- **Plugin version:** see `ICAIS_VERSION` in `config/constants.php` (currently 1.3.10)

---

## 2. Tech Stack & Build

- **Bundler:** Vite 8 (uses Rolldown — config key is `build.rolldownOptions`).
  Entry inputs are listed in `vite.config.js` (`inputs` array).
- **Dev:** `npm run dev` (port 4000, HMR). Prod: `npm run prod` → `assets/`.
- **Lint:** `npm run lint` / `lint:fix`. Validate: `npm run validate`.
- **Quality gates:** Husky pre-commit runs ESLint on staged `.js`/`.vue`.
- **Dist:** `./build.sh`.
- **Dev/prod asset resolution:** `ICAIS_DEV_MODE` (true when `.dev` file exists).

---

## 3. Codebase Map

### PHP (`includes/`)
- **Bootstrap:** `App.php` (singleton, `$loadOrder`), `Enqueue.php`,
  `EnqueueAsset.php`, `Menu.php`, `Install.php` (DB + migrations),
  `AutoGenerate.php` (alt text on upload), `AdminNotice.php`.
- **HTTP:** `Http/api.php` (all routes), `Http/Routes/{Route,Router}.php`,
  `Http/Controllers/` — Generator, Scanner, Batch, Settings, Stats,
  FileRenamer, WooCommerce.
- **Models (`Models/`):** Eloquent-like ORM — `ApiSetting`, `History`, `Stats`,
  `BatchJob` (+ `Model.php` base, WP object cache).
- **Services (`Services/`):** `Settings.php`, `Encryption.php`, `Database.php`,
  `FileRenamer.php`, `StatsTracker.php`, `SeoIntegration.php`, `UsageLimiter.php`,
  `TranslationStrings.php`, `WooCommerce.php`.
- **AI (`Services/AI/`):** `AIFactory.php`, `BaseAIProvider.php`,
  `ChatCompletionsProvider.php`; providers — `AnthropicProvider`,
  `OpenAIProvider`, `GeminiProvider`, **`WP7Provider`** (WordPress Native AI
  Client, opt-in). Planned (commented): Mistral, OpenRouter.
- **Support (`Support/`):** `AltTextHelper.php`, `Collection.php`, `Permission.php`
  (single role-based permission gate for all features — `isAllowed()`).
- **Middleware (`Http/Middleware/`):** `RoleGate.php` (gates the `generator`, `compressor`,
  and `renamer` route groups via `Permission::isAllowed()`).

### Frontend (`resources/admin/`)
- **Entry:** `main.js` → Vue app on `#imagecraft-app`.
- **Router:** `router.js` (hash history; `beforeEach` wizard guard; 5s settings cache).
- **API client:** `services/Api.js` (reads `window.imageCraft`).
- **Views:** Dashboard, Scanner, Batch, Settings, **SetupWizard**, WooCommerce, Help.
- **Settings sub-routes (`components/Settings/`):** General, Api, AltText, Title,
  Rename, WooCommerce, Seo, Integration, License.
- **Shared UI (`@ui/`):** Notification, Pagination. Plugins: `notification.js`.
- **Standalone JS entries:** `js/gutenberg-alt-button.js`, `js/block-editor-common.js`,
  `js/kadence-alt-button.js`, `js/spectra-alt-button.js`, `js/media-button.js`.

---

## 4. Features

| Feature | Backend | Frontend |
|---------|---------|----------|
| AI alt-text generation | GeneratorController, AIFactory, StatsTracker | Scanner.vue |
| Batch processing | BatchController, BatchJob | Batch.vue |
| Media library scan | ScannerController | Scanner.vue |
| Setup wizard | SettingsController (`completeWizard`) | SetupWizard.vue |
| Settings (all) | SettingsController, Settings | Settings.vue + sub-components |
| API key storage | ApiSetting, Encryption | ApiSettings.vue |
| **WordPress Native AI Connector** | WP7Provider, AIFactory | ApiSettings.vue + SetupWizard.vue |
| Auto-generate on upload | AutoGenerate.php | (none — WP hook) |
| File rename (SEO) | FileRenamerController, FileRenamer | Scanner.vue |
| **Image compression** | CompressorController, ImageCompressor, Compression, UsageLimiter (`canCompress`), BatchController (`type=compress`); **Pro unlocks via filters + auto-compress in the Pro add-on** | Compression.vue, CompressionSettings.vue |
| WooCommerce images | WooCommerceController, WooCommerce | WooCommerce.vue |
| SEO integration (Yoast/RankMath) | SeoIntegration | SeoSettings.vue |
| Stats / dashboard | StatsController, Stats, CompressorController (`stats`) | Dashboard.vue (dual-focus: Alt Text + Compression panels) |
| Usage rate limiting | UsageLimiter | (n/a) |
| License management (Pro) | (license-manage branch) | LicenseSettings.vue |

---

## 5. Key Flows

### Setup Wizard gating (`router.js:96`)
```
isComplete = wizard_completed && (api_setting.has_key || site_has_key)
```
- `wizard_completed` (from `GET /settings`) = `isWizardCompleted(user)` OR
  `ICAIS_API_KEY` config constant defined.
- `site_has_key` = config key defined, OR (`icais_use_wp7_ai` enabled AND WP7
  available), OR any active DB key site-wide.
- Incomplete → redirected to `/wizard`. Settings cached 5s; `clearSettingsCache()`
  after saving.

### Setup Wizard steps (`SetupWizard.vue`)
1. Welcome. 2. **Choose provider** — Anthropic / OpenAI / Gemini cards, plus a
   **WordPress Native AI Connector** card shown only when `wp7_available`.
   3. Configure: BYOK key input, OR config-key-detected panel, OR (for `wp7`) a
   "WordPress AI Client detected" panel with Test Connection + Save & Continue.
   4. Success.
   - Selecting the WP7 card → `saveWP7AndComplete()` sets `icais_use_wp7_ai='1'`
     then completes the wizard (no API key needed). Model selector is hidden for wp7.

### AI provider resolution (`AIFactory::getProviderForUser()`)
1. `ICAIS_API_KEY` wp-config constant (highest priority).
2. WP7 native client if `icais_use_wp7_ai` enabled AND available.
3. Database-stored encrypted BYOK key (site-wide active `ApiSetting`).
   - A saved BYOK key is never overridden by the native connector.

### Generation
Scanner/Batch/WooCommerce/AutoGenerate → `AIFactory::getProviderForUser()` →
provider `generateAltText()` → `StatsTracker` records `History` + `Stats`.

---

## 6. REST API (`/wp-json/icais/v1`)

Routes defined in `Http/api.php`. Groups: `settings/*`, `scanner/*`,
`generator/*`, `batch/*`, `stats/*`, `renamer/*`, `compressor/*`, `woocommerce/*`,
`filebird/*`, `notice/dismiss`, `feedback/deactivation`. Each route has a capability check
(`manage_options`, `upload_files`, `manage_woocommerce`, `edit_posts`, or
`activate_plugins`). **Full table lives in `CLAUDE.md` → REST API** (keep both in sync).

Key for the wizard/WP7 work:
- `GET /settings` returns `wizard_completed`, `api_setting{provider,has_key,source}`,
  `config_api_key`, `site_has_key`, `wp7_available`, `available_models`, etc.
- `POST /settings` — generic settings save (used to set `icais_use_wp7_ai`).
- `POST /settings/test-api` — `{provider:'wp7'}` tests the native connector
  (no key/model needed).
- `POST /settings/wizard/complete` — marks wizard done (user meta + site option).

---

## 7. Database (prefix `{$wpdb->prefix}icais_`)

- `api_settings` — encrypted keys (user_id, provider, encrypted_key, iv)
- `history` — generation history (attachment_id, original/generated alt, status, tokens, cost)
- `stats` — daily per-user stats
- `batch_jobs` — batch tracking
- `compressions` — compression history (attachment_id, user_id, original/compressed size, saved bytes/percent, method, backup_path, status) — added db_version 1.1.0
Schema changes go in `Install.php` with a version-gated migration. `Install::DB_VERSION`
(1.1.0) + `maybeUpgrade()` (runs on `plugins_loaded`, `try/catch \Throwable`) upgrade existing installs.

---

## 8. Settings & Constants

- `wp_options` keys are double-prefixed: stored as `icais_icais_*` (intentional
  legacy). Access via `Settings::get()/set()`; defaults in `Settings::$defaults`.
- Notable: `icais_use_wp7_ai` ('0' default, opt-in), `icais_api_provider`,
  `icais_<provider>_model`, `icais_request_timeout`, `icais_default_tone`,
  `icais_max_length`. Wizard flag: `icais_wizard_completed` (user meta + site option).
- Compression (all opt-in, default off): `icais_compression_enabled`, `icais_compression_quality`
  (85, visually lossless; Pro-adjustable), `icais_compression_max_width` (2048 longest edge; free
  fixed via `ImageCompressor::FREE_MAX_DIM`, Pro-adjustable), `icais_compression_convert_webp`
  (Pro, future), `icais_compression_keep_backup` (forced on), `icais_compression_skip_compressed`,
  `icais_compression_min_size_kb` (50), `icais_compression_auto` (Pro). Engine: direct Imagick
  (strip metadata + progressive JPEG, no chroma subsampling) with GD/`wp_get_image_editor`
  fallback; writes to temp + **never-enlarge guard** (commits only if smaller). Free cap 20/day
  via `UsageLimiter` separate counter `icais_daily_compress_<id>`; `ICAIS_PRO` removes the cap.
  Pro/free split: the engine reads quality/resize via `apply_filters('icais_compression_quality',85)`
  and `apply_filters('icais_compression_max_dim',2048)`; the **Pro add-on**
  (`imagecraft-...-pro/includes/Features/Compression.php`) overrides them + registers
  auto-compress-on-upload, ONLY when licensed. No auto-compress code ships in the free plugin.
- Constants (`config/constants.php`): `ICAIS_DEV_MODE`, `ICAIS_API_NAMESPACE`
  (`icais/v1`), `ICAIS_TABLE_PREFIX` (`icais_`), `ICAIS_VERSION`.
- API keys: AES-256-CBC via `Encryption.php` (WP `AUTH_KEY`/`SECURE_AUTH_KEY`).

---

## 9. AI Providers

`BaseAIProvider` handles image fetch/base64/MIME, tone prompts (professional,
casual, technical, seo, woo_*, custom), max length, multi-model.

Default models: Anthropic `claude-haiku-4-5`, OpenAI `gpt-4o-mini`,
Gemini `gemini-2.5-flash`. WP7 uses WordPress-managed models (no selection).

---

## 10. Recent Changes (newest first)

- **2026-06-25** — **Non-admin SPA access for permitted roles.** Permitted roles now also
  get the ImageCraft admin menu + SPA, limited to **Dashboard + Image SEO + Compress**;
  Settings/Batch/License/WooCommerce/Wizard stay admin-only. `Menu.php` shows the menu to
  permitted non-admins (`upload_files` cap; admins keep `manage_options`). `Enqueue.php` adds
  `isAdmin`/`wizardCompleted`/`canUse` to `window.icais`. Frontend gates: `router.js` guard
  blocks admin-only routes + skips the admin-only `/settings` fetch for non-admins;
  `Navbar.vue` shows only the three base tabs; `Scanner.vue`/`Compression.vue` hide bulk
  `batch/*` actions (keep per-image); `Dashboard.vue` skips settings/wizard check + admin-only
  CTAs. Pre-existing limit: `GeneratorController` ownership check — non-admins generate only
  for their own uploads. Additive/backwards-compatible (empty allow-list = no non-admin sees
  the menu, identical to before).
- **2026-06-25** — Added a **single role-based permission for all ImageCraft features**
  on a dedicated **Permissions** settings page (`components/Settings/PermissionsSettings.vue`,
  route `/settings/permissions`, new sidebar item in `Settings.vue`). One setting
  `icais_allowed_roles` (JSON array of role slugs; **empty = no restriction**,
  backwards-compatible default; administrators always allowed) governs alt text, image
  titles, file rename, and image compression. Enforced server-side by `Http\Middleware\RoleGate`
  on the `generator`, `compressor`, AND `renamer` route groups (after the capability check)
  plus a guard in `AutoGenerate::onAttachmentAdded`; logic centralized in
  `Support\Permission::isAllowed()`. `SettingsController::index()` returns `available_roles`
  (excludes `administrator`); `update()` sanitizes/validates `icais_allowed_roles`. Manual
  entry points (`media-button.js`, `gutenberg-alt-button.js`) hide ALL buttons
  (Generate/Title/Rename/Compress) when the `canUse` flag (in `icaisMediaButton`/`icaisGutenberg`
  via `Enqueue.php`) is false. Additive only — no breaking changes.
  (Supersedes the earlier alt-text-only iteration from the same day.)
- **2026-06-24** — Redesign: **dual-focus dashboard** (`resources/admin/views/Dashboard.vue`).
  Rebuilt as two equal feature panels side by side — **AI Alt Text** (SEO score ring, missing
  count, daily-usage bar, Scan/WooCommerce CTAs, folded-in API status) and **Image Compression**
  (total saved, compressed count, "not yet optimized", daily compress-usage bar, Compress CTA) —
  followed by a slim, labeled **Alt text health** row of three actionable problem chips
  (Missing / Generic / Too-long) that deep-link into Scanner filters. Now also calls
  `GET /compressor/stats`. Removed clutter: the 30-day Usage/Cost chart, the Generation-Results
  pie, the standalone API-status and Quick-Actions cards, the standalone daily-usage card, and
  the Recent Activity feed (`useECharts` no longer used here). Frontend-only; no API/DB changes.

- **2026-06-21** — New: **Compress button in the WordPress media modal**. The standalone
  `resources/admin/js/media-button.js` (which already injects Generate/Title/Rename buttons into
  attachment details) now also shows a **Compress** button when `icais_compression_enabled`,
  calling `POST /compressor/compress` and refreshing the attachment on success. New flag +
  i18n in `Enqueue.php` (`icaisMediaButton.compressionEnabled`). Vite input → rebuilt.

- **2026-06-21** — Refactor: **compression Pro/free split for maintainability + code
  separation**. The free engine is now Pro-agnostic — it reads quality/resize via
  `apply_filters('icais_compression_quality',85)` and `apply_filters('icais_compression_max_dim',2048)`.
  Removed `includes/AutoCompress.php` from the free plugin. The **Pro add-on** now owns the
  premium behavior in `imagecraft-...-pro/includes/Features/Compression.php` (registered only when
  the license is valid): it overrides those filters with the user's settings and registers
  auto-compress-on-upload (`add_attachment` → `icais_auto_compress` cron → free `ImageCompressor`,
  all `class_exists`/`\Throwable`-guarded). The 20/day cap bypass still rides `ICAIS_PRO`. Net:
  premium *behavior* no longer ships in WordPress.org source; engine/UI/upsell stay in free.

- **2026-06-20** — New: **Image compression** feature (freemium, local engine).
  Re-encodes images via `wp_get_image_editor` (+ optional max-width downscale),
  in place so URLs never change; originals are always backed up to
  `uploads/icais-originals/` with one-click **Restore**. New "Compress" page
  (`Compression.vue`) scans the library, shows per-image size + savings, supports
  single compress/restore and **bulk** compress via the existing batch engine
  (`/batch/start` `type:'compress'`). Pro-gated via `ICAIS_PRO`: free = manual
  compress at fixed quality 80 with a **20/day** cap (separate `UsageLimiter`
  counter); Pro = unlimited + adjustable quality + downscale + **auto-compress on
  upload** (`AutoCompress.php`, opt-in). New files: `Services/ImageCompressor.php`,
  `Models/Compression.php`, `Http/Controllers/CompressorController.php`,
  `includes/AutoCompress.php`, `views/Compression.vue`,
  `components/Settings/CompressionSettings.vue`. Engine: direct **Imagick** pass (strip
  metadata + progressive JPEG + 4:2:0 chroma) with GD/`wp_get_image_editor` fallback;
  defaults quality **75** + downscale to **1920px** longest edge (free fixed, Pro adjustable)
  → ~30–60% typical savings. New table `icais_compressions`
  (db_version **1.1.0** via `Install::maybeUpgrade()`). Routes: `compressor/*`.
  All code lives in the FREE plugin; the Pro add-on only flips the `ICAIS_PRO`
  flag. WebP format-conversion intentionally deferred (would change URLs).
  Fatal-safety verified (Throwable guards on editor/filesystem/cron/migration).

- **2026-06-19** — New: **Deactivation feedback survey**. A modal appears when an
  admin clicks "Deactivate" on the ImageCraft row of the Plugins screen, with a
  reason list (no longer need / found better / couldn't get it to work /
  temporary / don't know how to use / other + comment) and two buttons:
  "Submit & Deactivate" and "Skip & Deactivate". Submit posts the response to
  `POST /feedback/deactivation` (`activate_plugins`), which forwards it to the
  Softminal endpoint via `wp_remote_post` (filterable via `icais_feedback_endpoint`
  / `ICAIS_FEEDBACK_ENDPOINT`, default `https://admin.softminal.com/wp-json/softminal/v1/feedback`).
  Skip sends nothing; deactivation always proceeds. New files:
  `includes/DeactivationSurvey.php`, `includes/Http/Controllers/FeedbackController.php`,
  `resources/admin/css/deactivation-survey.css`. Self-contained (renders on
  `plugins.php`, outside the Vue SPA; no Vite rebuild needed). readme.txt
  External services section updated to disclose the Softminal endpoint. Not yet
  released — will ship in a future version.
- **2026-06-19** — Released **1.3.10**: WordPress Native AI Connector reliability
  fix. `WP7Provider::isAvailable()` (used to decide whether to show the connector
  option in API Settings + Setup Wizard) previously called
  `is_supported_for_text_generation()`, which makes a live `listModels` network
  request to the configured provider on every settings-page load. With no
  persistent object cache the probe ran each request and intermittently failed
  on a network blip, hiding the connector option even when correctly configured.
  Now gated on cheap local checks only (`function_exists('wp_ai_client_prompt')`
  + `wp_supports_ai()`); the live capability check remains in `testConnection()`
  and the generation path. Also wrapped the availability resolution in
  `SettingsController::index` in `try/catch (\Throwable)` so a core AI Client
  fatal can never 500 the settings endpoint. Version bumped in main plugin file,
  `config/constants.php`, `package.json`, and `readme.txt` (Stable tag +
  changelog + upgrade notice).
- **2026-06-15** — Released **1.3.9**: UI enhancements. Added a "Get Pro" button
  (solid amber, white text + SVG star) to the Navbar, Dashboard daily-usage card,
  and the non-Pro License settings upsell; hidden when `window.icais.isPro`. The
  Settings sidebar now always shows a **License** item — Pro users
  (`isProInstalled`) see license management, others see an upsell with Get Pro.
  Version bumped in main plugin file, `config/constants.php`, `package.json`, and
  `readme.txt` (Stable tag + changelog + upgrade notice).
- **2026-06-15** — Fix (`Dashboard.vue:424`): the dashboard's setup-complete
  check only looked at `api_setting?.has_key`, so users who finished setup via
  the WordPress Native AI Connector (`api_setting` is `null`, `site_has_key`
  true) were bounced and the dashboard loaded no data (blank stats, audit, and
  usage card). Now mirrors the router guard: `wizard_completed && (has_key ||
  site_has_key)`.
- **2026-06-15** — Released **1.3.8**: version bumped in main plugin file,
  `config/constants.php` (`ICAIS_VERSION`), and `readme.txt` (Stable tag +
  changelog + upgrade notice). Headline: the WordPress Native AI Connector
  wizard card (below).
- **2026-06-14** — Setup Wizard: added "WordPress Native AI Connector" provider
  card (shown when `wp7_available`); selecting it enables `icais_use_wp7_ai` and
  completes the wizard with no API key. Frontend-only (`SetupWizard.vue`); reuses
  existing `POST /settings`, `POST /settings/test-api` (`wp7`), and
  `POST /settings/wizard/complete`. Fixes fresh installs being forced through
  key entry when a native connector is already active.
- License management settings page for Pro (`license-manage` branch).
- Plugin config made site-wide; improved image error messages.

---

## MAINTENANCE RULE (Claude must follow)

Whenever you add, remove, or change a **feature, REST endpoint, Vue
view/route, PHP controller/service/model, DB table/column, `wp_options` key, AI
provider, or a runtime flow**, update the relevant section(s) above AND prepend a
dated entry to **Recent Changes**. Keep it concise and scannable. This file
complements `CLAUDE.md` (which holds the authoritative dependency map, full REST
table, and conventions) — when both describe the same thing, keep them in sync.
