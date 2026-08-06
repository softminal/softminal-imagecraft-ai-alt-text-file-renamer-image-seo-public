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
  `AutoGenerate.php` (alt text on upload), `AdminNotice.php`,
  `Fallback.php` (Image Fallbacks — the plugin's only public-frontend surface;
  broken-`<img>` JS swap + `_thumbnail_id` meta-filter featured fallback; opt-in,
  non-destructive).
- **HTTP:** `Http/api.php` (all routes), `Http/Routes/{Route,Router}.php`,
  `Http/Controllers/` — Generator, Scanner, Batch, Settings, Stats,
  FileRenamer, WooCommerce, Fallback (time-budgeted broken-image scan;
  `scanBrokenCore()` reusable by the Pro cron).
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
  Rename, WooCommerce, Seo, Integration, License, Fallback (`/settings/fallback`,
  uses shared `ImagePick.vue` wp.media picker).
- **Public frontend JS (`resources/public/js/`):** `fallback.js` — raw (non-Vite)
  broken-image onerror swap; copied into releases by `build.sh`.
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
| **Image Fallbacks** (broken + missing featured) | Fallback.php (frontend hooks), FallbackController (scan); **Pro: per-post-type images via `icais_fallback_featured_image_id` filter + scheduled scan/email cron in the Pro add-on** | FallbackSettings.vue, ImagePick.vue, Dashboard.vue (Broken images tile + score penalty), resources/public/js/fallback.js |
| WooCommerce images | WooCommerceController, WooCommerce | WooCommerce.vue |
| SEO integration (Yoast/RankMath) | SeoIntegration | SeoSettings.vue |
| Stats / dashboard | StatsController, Stats, CompressorController (`stats`) | Dashboard.vue (dual-focus: Alt Text + Compression panels) |
| Usage rate limiting | UsageLimiter | (n/a) |
| License management (Pro) | (license-manage branch) | LicenseSettings.vue |

---

## 5. Key Flows

### Setup Wizard gating (`router.js`)
```
isComplete = wizard_completed        // API key NO LONGER required
```
- An API key is not needed to enter the SPA — the plugin works as a compression-only
  tool. `wizard_completed` (from `GET /settings`) = `isWizardCompleted(user)` OR
  `ICAIS_API_KEY` config constant defined.
- The wizard is **skippable without a key** (`SetupWizard.vue` `skipWizard()` →
  `/settings/wizard/complete`). Incomplete → redirected to `/wizard`; once completed or
  skipped, the SPA opens. Settings cached 5s; `clearSettingsCache()` after saving.
- `window.icais.hasAiKey` (server-computed, guarded) → inline "Add API key" prompt on
  Dashboard/Scanner when no provider is configured. `site_has_key` (config / WP7 / active
  DB key) is still returned by `GET /settings` for the Settings UI.

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
`generator/*`, `batch/*`, `stats/*`, `renamer/*`, `compressor/*`, `fallback/*`,
`woocommerce/*`, `filebird/*`, `notice/dismiss`, `feedback/deactivation`. Each route has a capability check
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
  fallback; writes to temp + **never-enlarge guard** (commits only if smaller). Free cap: the
  **shared 50-action / rolling-24h pool** in `UsageLimiter` (`icais_daily_usage_<userId>`,
  `compress_count` field) — the SAME pool as generation, not a separate counter;
  `ICAIS_PRO` removes the cap.
  Pro/free split: the engine reads quality/resize via `apply_filters('icais_compression_quality',85)`
  and `apply_filters('icais_compression_max_dim',2048)`; the **Pro add-on**
  (`imagecraft-...-pro/includes/Features/Compression.php`) overrides them + registers
  auto-compress-on-upload, ONLY when licensed. No auto-compress code ships in the free plugin.
- Combined "Generate All" (all opt-in): `icais_meta_enabled` (master, off), `icais_meta_include_alt`,
  `icais_meta_include_title`, `icais_meta_include_caption` (all on), `icais_meta_auto_generate`
  (Pro-gated effect), `icais_caption_max_length` (150), `icais_caption_default_tone`,
  `icais_caption_skip_existing`. Single combined AI call → JSON {alt_text,title,caption}; counts as 1.
  Auto-on-upload is Pro-only (free exposes `GeneratorController::generateMetaForImage()` + Pro-locked
  UI toggle; the Pro add-on owns the `add_attachment` handler — same split as auto-compress).
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

## 9b. Next-Release Targets (decided 2026-08-06 — NOT in the current ship)

- **GIF conversion/compression** — extend the Next-gen/compression engines to GIF.
  Caution noted: static GIF→WebP is straightforward (GD/Imagick); **animated** GIF
  needs Imagick with frame coalescing and is often unavailable on shared hosts —
  ship static-GIF-only first, clearly labeled, with capability detection like AVIF.
- **PDF optimization** — evaluate scope carefully (different engine domain than
  images); explicitly deferred from the current release.

The current release ships WITHOUT GIF and PDF handling — feature parity with
Kraken.io on everything else (compress/convert/resize/bulk/auto; JPEG/PNG/WebP/AVIF),
plus local-only processing and the Image-SEO pillar Kraken lacks.

## 10. Recent Changes (newest first)

- **2026-08-07** — **Dashboard AI-setup card redesign + "What's New" notice revived for
  WebP/Fallbacks.** (1) The Dashboard no-key banner is now a design-system card (KeyRound
  icon tile, heading + body, brand CTA button) and **dismissible** (X, localStorage
  `icais_ai_prompt_dismissed`, same pattern as the next-gen hint); the Scanner keeps its
  own contextual prompt so AI setup is never hidden where needed. New copy also mentions
  the WordPress AI Client and no-markup billing. (2) The retired wp-admin "What's New"
  notice returns as `AdminNotice::displayNextGenFeatureNotice` announcing **Next-gen WebP
  + Image Fallbacks** together: admins only, own per-user meta
  `icais_nextgen_notice_dismissed` (so users who dismissed the old Fallbacks-only notice
  still hear about WebP), hidden mid-wizard and once BOTH features are enabled, CTA →
  `#/performance`. Dismiss endpoint gains additive `notice=nextgen_feature`; the setup
  wizard auto-dismisses it for fresh installs. Legacy `displayFallbackFeatureNotice`
  stays unhooked (BC only). All new UI strings registered + translated in all 8 locales
  (catalogs at 947/947, msgfmt -c clean).

- **2026-08-07** — **Shipped translations for 8 locales + textdomain loader.** Discovery:
  the plugin never called `load_plugin_textdomain`, so the bundled es_ES/fr_FR .mo files
  were NEVER loaded (any working translations came from the wp.org language pack, which
  only covers old strings). Fix: `init`-hooked `load_plugin_textdomain` in the main plugin
  file (WP_LANG_DIR packs still take priority). Regenerated the POT (942 strings,
  `wp i18n make-pot`, `--skip-js`, excludes build/node_modules/docs/assets/resources) and
  shipped COMPLETE 942/942 catalogs for **es_ES, fr_FR (274 community strings preserved
  via msgmerge), de_DE, it_IT, pt_BR, ar, ja, nl_NL** — matching the B2B Suite locale set.
  All machine-authored (informal register where that's the WP-core convention: du/tú/tu/je),
  validated with `msgfmt -c` + placeholder-integrity checks; native review welcome, and
  translate.wordpress.org strings will override once approved. Pipeline (msgids.json +
  index-keyed chunk files + apply_po.py) lives in the session scratchpad; regenerate any
  locale by re-running make-pot → msgmerge → fill → msgfmt.

- **2026-08-07** — **i18n catch-up + Plugin Check pass.** 297 `$t()` strings used in the
  SPA were never registered in `TranslationStrings.php` (everything since the UX redesign:
  dashboard pillar cards, Compression, Next-gen, Fallbacks, Permissions, Meta, Batch
  details, WooCommerce modal, Help) — unregistered strings can NEVER be translated because
  `window.icais.i18n` is built from that file. All added in new `viewStrings()` method
  (auto-collected; translator comments on placeholder strings). Rule of thumb: any new
  `$t()` string MUST also be added to `TranslationStrings.php`. Also fixed from PCP:
  `unlink()` → `wp_delete_file()` in `NextGenConverter.php` (removal now verified via
  `!is_file()` since `wp_delete_file()` has no return on older WP), readme tags 6 → 5
  (dropped `image converter`), `'Saved %s (%s%)'` → numbered `%1$s/%2$s` placeholders.
  Remaining PCP output is dev-only files excluded by `.distignore`/`build.sh` + two
  accepted WP_DEBUG-gated `error_log` warnings.

- **2026-08-07** — **Onboarding overhaul (audit fixes).** `SetupWizard.vue` rewritten on the
  design system (`--ic-*` tokens, `@ui` UiButton/UiBadge, Lucide icons, no emoji) with a
  4-stage step indicator. Fixes from the onboarding audit: (1) **compliance** — tutorial
  videos (wizard + Help.vue) now use new `components/VideoEmbed.vue`: click-to-load
  `youtube-nocookie.com`, zero external requests until the user clicks; readme
  `== External services ==` now discloses YouTube AND the Softminal deactivation-feedback
  endpoint. (2) **honesty** — the false "Unlimited" welcome card replaced with the real free
  tier (50 actions/day shared pool); provider cards show approximate per-image costs
  (Haiku ~$0.0008 / GPT-4o mini ~$0.003 / Gemini Flash ~$0.001) instead of contradictory
  adjectives. (3) **bug** — a `watch` on provider+key clears `testResult`, closing the
  save-an-untested-key hole. (4) **a11y** — provider cards are `<button role="radio">` with
  focus rings (were click-only divs). (5) **i18n** — every wizard string now in
  `TranslationStrings.php` (fallback step, WP7/config-key variants, video strings); stale
  keys for removed copy dropped, duplicate keys removed. (6) **UX** — skip path
  (`skipToFallbacks`) marks the wizard complete then routes to the key-free Fallbacks step
  instead of dead-ending at the dashboard; welcome pitch covers both pillars; stale
  "GPT-4" / "AI Alt Text Generator" copy gone; Gemini key URL updated to
  aistudio.google.com. No PHP behavior change (TranslationStrings only); REST/API untouched.

- **2026-08-07** — **Docs correction: usage limit is ONE shared pool, not two counters.**
  `CLAUDE.md` and this file described the free tier as two separate counters (50/day
  generation + 20/day compression, key `icais_daily_compress_<id>`). The code has never
  matched that since the pool was consolidated: `UsageLimiter` uses a single
  `DAILY_LIMIT = 50` over a **rolling 24h window** (`WINDOW_SECONDS`), stored in one option
  `icais_daily_usage_<userId>` as `['started_at','ids','compress_count']`, with
  `totalCount() = count(ids) + compress_count` spanning generation AND compression. The
  `icais_daily_compress_<id>` key does not exist. Generation is deduped per attachment
  (re-generating a counted image is free); every compression increments. No code change —
  documentation only. Older dated entries below are left as-is as a historical record.
  **Also:** plugin display name changed to *ImageCraft - AI Alt Text, Image Compression &
  WebP* (`readme.txt` + `Plugin Name` header; slug, text domain, and version unchanged).

- **2026-08-06** — **Premium UX redesign (two-pillar IA + design system) + Next-gen
  formats (WebP/AVIF).** One release, five build phases. (1) Design system:
  `resources/admin/design/tokens.css` (`--ic-*` vars — brand blue, pillar accents
  SEO=blue/Performance=teal) bridged into Tailwind v4 via `@theme inline` in
  `resources/scss/index.css`; new `@ui` primitives (UiCard/UiButton/UiBadge/UiField/
  UiPageHead/UiSectionHeader/UiEmptyState/UiSkeleton); Lucide icons replace emoji.
  (2) Single navigation model `config/navigation.js` (topNav, settingsSections,
  pillarScreens, quickActions) drives Navbar, Settings sidebar, new `SectionBar.vue`
  contextual sub-bar, and Dashboard quick actions; canonical `/performance` route
  (`/compression` redirects). (3) Settings sidebar regrouped General / Image SEO /
  Performance with per-item descriptions; Integrations children visible. (4) Dashboard
  reflowed to two pillar health cards + calm secondary row; Fallbacks promo card and
  wp-admin "What's New" notice RETIRED (dismiss endpoint/meta kept); Get Pro reduced
  to one quiet Navbar entry. (5) NEW Next-gen formats: `Services/NextGenConverter.php`
  sidecar engine (photo.jpg.webp/.avif beside originals, `_icais_nextgen` manifest,
  capability probe, never-bigger guard), `includes/NextGen.php` `<picture>` delivery
  (opt-in, default off, `wp_content_img_tag` + `post_thumbnail_html`, delete_attachment
  cleanup always on), REST `POST /compressor/convert` + `GET /compressor/convert-scan`
  + `POST /compressor/convert-clean`, batch `type='convert'`, shared 20/day compress
  counter. New settings (additive, default off): `icais_compression_convert_avif`,
  `icais_nextgen_delivery_enabled`, `icais_nextgen_auto` (+ dormant
  `icais_compression_convert_webp` now live). `window.icais.canWebp/canAvif`.
  New `/settings/nextgen` page. Pro add-on gained `Features/NextGen.php`
  (auto-convert-on-upload; AVIF unlocked via `ICAIS_PRO` in the free engine).
  Free = manual WebP + delivery; Pro = AVIF + auto + no cap. No DB migration.

- **2026-07-11** — **Update-discovery + Quick Actions.** (1) One-time wp-admin "What's New:
  Image Fallbacks" notice (`AdminNotice::displayFallbackFeatureNotice`) — admins only, per-user
  dismissal (`icais_fallback_notice_dismissed`, cleaned on uninstall), hidden when the module is
  on or wizard incomplete; the wizard's Fallbacks step auto-dismisses it so fresh installs never
  see it. `POST /notice/dismiss` gained an additive `notice` param (`review`|`fallback_feature`);
  the dismiss inline JS was refactored into a shared `printDismissScript()`. (2) Dashboard
  **Quick actions** widget: card grid → Generate alt text / Compress / Rename / Set up fallbacks
  (admin) / WooCommerce images (admin + Woo). Both verified with Playwright (notice shows,
  dismiss persists across reload, widget navigates).

- **2026-07-11** — **NEW: Image Fallbacks module (opt-in, default off; first public-frontend
  surface).** (1) Broken-image fallback: raw JS (`resources/public/js/fallback.js`, capture-phase
  error listener + already-failed sweep) swaps any broken `<img>` to a chosen fallback attachment
  and fills empty alt (SEO-aware). (2) Missing-featured-image fallback: `get_post_metadata`
  (`_thumbnail_id`) filter (primary; `is_admin()` bail, recursion guard) + `post_thumbnail_html`
  (secondary) — non-destructive, nothing written to DB. (3) Admin broken-image scan:
  `GET /fallback/scan-broken` (upload_files), time-budgeted + resumable via `icais_broken_scan`
  transient (`partial:true` → re-call); Dashboard "Broken images" tile + ≤10-pt score penalty.
  New: `includes/Fallback.php`, `FallbackController.php`, `FallbackSettings.vue`, `ImagePick.vue`,
  route `/settings/fallback`; `wp_enqueue_media()` on the ImageCraft page; `build.sh` now copies
  `resources/public/`. Settings: `icais_fallback_{enabled,broken_enabled,broken_image_id,
  featured_enabled,featured_image_id,featured_post_types}` + Pro-effect (inert in free)
  `icais_fallback_{featured_map,scan_schedule,scan_email}`. **Pro split (mirrors Compression):**
  free = global fallback image + manual scan; Pro = per-post-type images via
  `apply_filters('icais_fallback_featured_image_id')` + `icais_fallback_scan` cron with
  email-on-new-broken (Pro add-on `Features/Fallbacks.php`; free registers NO cron).
  **Discovery UX:** Dashboard promo card (admins, while module off, localStorage-dismissible);
  SetupWizard step 4 = optional one-picker Fallbacks setup (one image for both sub-features;
  wizard completes before the step so abandoning is safe; step 5 = success screen); the
  Broken-images tile scans even when the module is off. FallbackSettings adds inline
  "choose an image" warnings + "use the same image" shortcut.

- **2026-06-30** — **Auto-generate-on-upload is now Pro-only (hard cut).** The FREE plugin no
  longer auto-generates on upload (`App::run()` does not call `AutoGenerate::init()`). The single
  Pro `Features\Meta` handler (alt+title+caption) owns all auto-on-upload; its `autoEnabled()` also
  honors the legacy toggles so existing Pro users keep working. The per-page auto toggles (Alt Text,
  Image Title, WooCommerce) were removed; the only auto control is the Pro-locked one in
  `MetaSettings.vue` (+ post-type exclusion moved there). Legacy keys kept in defaults (inert).
  NOTE: this removes a previously-free feature from existing free users on update (intentional).
- **2026-06-30** — **Dashboard redesign.** Needs Attention moved to top; panels are now Image SEO
  (2/3) + Compression (compact 1/3 widget); per-ring "X missing" + compression "not optimized"
  removed (deduped vs the Needs Attention cards); new blended **Overall optimization** KPI card
  before Recent Activity (`overallScore` = avg of alt/title/caption coverage + compression coverage).
- **2026-06-29** — **Media modal: unified field picker.** The WP media-modal AI buttons
  (Generate with AI / Generate Title / Generate All) were replaced by a single **field picker**
  (Alt / Title / Description checkboxes + one Generate button), matching the bulk actions page.
  `generate-meta` REST now accepts an explicit `fields` list; `media-button.js` posts the ticked
  fields. Compress + Rename stay as separate buttons. The picker is always shown (canUse);
  checkbox defaults from `icaisMediaButton.metaIncludeAlt/Title/Caption`. `icais_meta_enabled` is
  now vestigial (no UI), so its settings toggle was removed; the key stays in defaults.
- **2026-06-29** — **Dashboard: Description coverage ring.** The Image SEO panel now shows a
  third ring (Alt text / Title / **Description**). `StatsController::audit` returns additive
  `caption_good/caption_missing/caption_score`, computed with one aggregate query counting image
  attachments with a non-empty `post_excerpt` (no per-image N+1). `Dashboard.vue` renders the ring
  via `captionColor`.
- **2026-06-29** — **Bulk generate field picker.** The Scanner bulk bar now shows
  [Alt text][Title][Description] checkboxes + a single **Generate (N)** button (replacing the
  separate Generate Alt/Title/All buttons). It fires one combined `meta` batch with only the
  ticked fields. Per-action selection overrides the `icais_meta_include_*` settings via a new
  `fields` param: `BatchController` stores it in `icais_batch_fields_<id>`,
  `GeneratorController::generateMetaForImage($fields)` + `BaseAIProvider::setMetaFields()` keep
  the AI prompt and saved fields in sync. Checkbox defaults come from the Generate-All settings
  (`window.icais.metaIncludeAlt/Title/Caption`). Media-modal per-image buttons unchanged.
- **2026-06-29** — **Wizard made skippable (compression-only / no-key users).** The SPA
  router guard no longer requires an API key — `isComplete = wizard_completed` only
  (`router.js`), mirrored in `Dashboard.vue`. `SetupWizard.vue` gains a **Skip** action
  (welcome + provider steps) that calls `/settings/wizard/complete` with no key, so
  compression-only users aren't trapped (skipping also un-gates the media-modal Compress
  button, which `Enqueue.php` ties to `isWizardCompleted()`). New `window.icais.hasAiKey`
  flag (`Enqueue.php`, guarded via `AIFactory::isConfigDefined()`/`isWP7Available()` +
  `ApiSetting::getActive()`) drives an inline "Add API key" prompt on Dashboard + Scanner
  when no provider is configured. Only loosens gating → fully backwards-compatible.
- **2026-06-29** — **"Generate All" combined metadata (alt + title + caption).** New opt-in
  feature that generates all three image fields in a **single AI call** returning strict JSON
  `{alt_text, title, caption}`. Backend: `BaseAIProvider::setMetaMode()/getMetaPrompt()`;
  `GeneratorController::generateMetaForImage()` core (public, reused by batch + Pro) +
  `generateMeta()`/`acceptMeta()` REST (`/generator/generate-meta`, `/generator/accept-meta`);
  `BatchController` `type:'meta'` branch. Caption → `post_excerpt` (+ `_icais_generated_caption`
  tracking). Counts as **1** generation. Frontend: `MetaSettings.vue` (`/settings/meta`), Scanner
  bulk "Generate All", media-modal per-image "Generate All" (`media-button.js`). New settings
  `icais_meta_*` / `icais_caption_*` (feature off by default; fields on). **Auto-on-upload is
  Pro-only** (mirrors auto-compress: free exposes the core + Pro-locked toggle, Pro add-on owns
  the `add_attachment` handler). Fully additive / backwards-compatible.
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
