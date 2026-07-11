# Image Fallbacks — Feature Plan

## Context

Add a new, **user-simple, non-destructive** feature in the spirit of two plugins the
user likes — *Senzastile Featured Image Fallbacks* and *Broken Image Fallback*. Both
are frontend-only, write nothing to the DB, and solve a *visible* problem
("no image ever looks broken").

We are adding a single **"Image Fallbacks"** module that combines both, with an
ImageCraft-specific **SEO-aware** twist (AI/attachment alt text on fallbacks + a
broken-image count in the Dashboard health score). It is fully opt-in (default off)
so existing installs are unaffected on update.

This is the plugin's **first public-frontend surface** — everything today is admin-only.
That is the main new risk area; the plan keeps it inside existing safe patterns.

### Locked decisions
1. **Both sub-features shipped as one module** (broken-image fallback + missing-featured-image fallback).
2. **SEO-aware:** fallback carries proper alt text; broken images reported in the Dashboard health score.
3. **Broken-image detection = admin-side server scan** (`get_attached_file()` + `file_exists()`),
   cached in a transient. **No public/logged-out REST endpoint** (avoids anonymous-abuse
   surface, WP.org scrutiny, and a new `Route::public()` abstraction). The visible JS
   `onerror` swap still fixes *all* broken images (incl. external/CDN) for visitors.
4. **Featured fallback = `get_post_metadata` (`_thumbnail_id`) filter as PRIMARY, `post_thumbnail_html`
   as secondary.** Rationale: `post_thumbnail_html` only fires when the theme actually calls
   `the_post_thumbnail()` — but nearly every theme guards with `if (has_post_thumbnail())` first, so
   for a post with no thumbnail that filter never runs and the fallback silently never appears (block
   themes' `core/post-featured-image` likewise renders nothing). Filtering `_thumbnail_id` meta makes
   `has_post_thumbnail()` return true, so classic themes, block themes, REST, and og:image all work —
   still non-destructive (nothing written to DB). This is how "Default Featured Image"-style plugins do it.
5. **No new DB table** — broken-scan results cached in a transient; keeps `DB_VERSION` unchanged.
6. **Free/Pro split (compression pattern):** free = global fallback image + manual scan;
   Pro = per-post-type fallback images + scheduled scan with email alert (see §H).

---

## What gets built

### A. New settings (opt-in, default off) — `includes/Services/Settings.php`
Append to `$defaults` (follow the existing `'0'` / JSON-string convention, e.g. line 49/50):
```php
'icais_fallback_enabled'              => '0',   // module master toggle
'icais_fallback_broken_enabled'       => '0',   // JS onerror swap
'icais_fallback_broken_image_id'      => 0,     // attachment shown for broken <img>
'icais_fallback_featured_enabled'     => '0',   // missing-featured-image fallback
'icais_fallback_featured_image_id'    => 0,     // attachment used as default featured image
'icais_fallback_featured_post_types'  => '[]',  // which post types (empty = all public)

// Pro-effect keys (stored in free defaults like icais_compression_quality; INERT in free)
'icais_fallback_featured_map'         => '{}',  // {post_type: attachment_id} per-type overrides (Pro)
'icais_fallback_scan_schedule'        => '0',   // '0' | 'daily' | 'weekly' scheduled broken scan (Pro)
'icais_fallback_scan_email'           => '1',   // email admin when scheduled scan finds NEW broken images (Pro)
```
No other change needed — `Settings::update()` only persists keys present in `$defaults`,
and `Install::uninstall()` auto-deletes all default keys. Double-prefix behavior is intentional (leave as-is).

### B. New frontend bootstrap class — `includes/Fallback.php`
New class `Softminal\ImageCraft\Fallback` with an instance `init()` (mirrors `Enqueue`/`Menu`).
Registered in `App.php`: add `'Fallback.php'` to the `includes` `files` list (~line 91),
instantiate in `initializeClasses()`, call `$this->fallback->init()` in `run()` — each guarded by `class_exists`.

`init()` registers (only when `icais_fallback_enabled` is on — check inside each callback, defensively):
- `add_action('wp_enqueue_scripts', ...)` → enqueue the raw frontend JS (see C) when
  `icais_fallback_broken_enabled` and a valid `icais_fallback_broken_image_id` exist.
- **PRIMARY — `add_filter('get_post_metadata', ...)`** (featured fallback): when the meta key is
  `_thumbnail_id` and the real value is empty, return the fallback attachment ID. Guards:
  - Bail on `is_admin()` (never pollute the editor/media modal/list tables with a fake thumbnail).
  - Bail unless `icais_fallback_featured_enabled` and post type is in the allow-list
    (empty allow-list = all public post types via `get_post_types(['public' => true])`).
  - **Recursion guard**: a static `$resolving` flag around the inner
    `get_post_meta($postId, '_thumbnail_id', true)` call (the filter re-enters itself).
  - Validate the fallback attachment still exists (`wp_attachment_is_image()`); return `null`
    (default behavior) otherwise.
  - Return type care: `get_post_metadata` with `$single=true` expects the raw value; with
    `$single=false` an array — mirror core semantics (`$single ? $id : [$id]`).
- **Secondary — `add_filter('post_thumbnail_html', ...)`**: if the HTML is still empty (edge cases
  where a theme bypasses the meta path), return `wp_get_attachment_image()` of the fallback
  (carries its alt text). Cheap belt-and-braces; both filters are **non-destructive**.

**Fatal-safety:** every callback null-checks (`wp_get_attachment_image_url()`/`get_post()` can
return false), uses only core WP functions (no optional deps), and is cheap. The
`get_post_metadata` filter is hot-path (runs on every meta read) — it must do its cheap bails
(key !== `_thumbnail_id`, `is_admin()`, feature off) FIRST, before any `Settings::get()`-heavy work,
and cache the parsed post-type allow-list in a static.

### C. Frontend JS (raw, non-Vite) — `resources/public/js/fallback.js`
Tiny vanilla script, enqueued directly like `deactivation-survey.css`:
```php
wp_enqueue_script('icais-fallback', ICAIS_URL . 'resources/public/js/fallback.js', [], ICAIS_VERSION, true);
wp_localize_script('icais-fallback', 'icaisFallback', [
    'imageUrl' => wp_get_attachment_image_url($id, 'full'),
    'altText'  => get_post_meta($id, '_wp_attachment_image_alt', true),
]);
```
Behavior — two correctness details are MANDATORY (a naive delegated listener ships broken):
1. **`error` events on `<img>` do NOT bubble.** The listener must be capture-phase:
   `document.addEventListener('error', handler, true)`. A normal (bubbling) delegated listener
   catches nothing.
2. **Initial sweep for images that failed BEFORE the script ran.** The script loads deferred in
   the footer, and an already-failed image never re-fires `error`. On `DOMContentLoaded` (or
   immediately, since deferred scripts run after parse), sweep
   `document.images` and swap any `img.complete && img.naturalWidth === 0 && img.src`.

Swap logic (shared by both paths): set `img.src = icaisFallback.imageUrl`; if the original `alt`
is empty set `img.alt = icaisFallback.altText` (property assignment only — never string-concatenate
into HTML). Infinite-loop guard: skip if `img.src` already equals the fallback URL or the element
carries a `data-icais-fallback` marker set on swap (marker is the robust check — the browser may
normalize the URL). Also skip `<img>` with empty/`data:` src. Zero dependency, `defer`, no impact
on page speed.
Do **not** route through `EnqueueAsset::script` (that resolves via the Vite manifest) — enqueue raw
with `ICAIS_URL` like the media-button CSS.

### D. Broken-image scan (admin) — new controller + route
- Route group in `includes/Http/api.php`: `Route::prefix('fallback')->capability('upload_files')->group(...)`
  with `GET /fallback/scan-broken`. Stays inside the existing `Route` DSL (logged-in admin only) —
  **no public endpoint, no new middleware.**
- New `includes/Http/Controllers/FallbackController.php` extending `Controller`:
  `scanBroken()` iterates image attachments (batched `WP_Query`, `fields=ids`, pages of ~500),
  resolves `get_attached_file($id)`, flags those where the file is missing on disk. Returns
  `{ success, broken_count, scanned, total, partial, sample: [{id, url, title}] }`.
  **Scale guard (large media libraries):** a full one-request scan of 20k+ attachments with
  `file_exists()` per image will hit `max_execution_time`. Scan under a **time budget** (~10s of
  wall clock, checked between pages): if the budget runs out, persist progress (cursor = last
  scanned page + running counts) in the transient and return `partial: true`; the frontend simply
  re-calls the endpoint until `partial` is false (same pattern as `batch/process` chunking).
  Cache the finished result in the transient (`icais_broken_scan`, `HOUR_IN_SECONDS`) so the
  Dashboard tile is cheap; support `?refresh=1` to bust and re-scan on demand.
  Wrap the whole method body in `try/catch (\Throwable)` and return the standard error `response()`.

### E. Dashboard health-score integration (SEO-aware) — `resources/admin/views/Dashboard.vue`
- Add a "Needs attention" tile: **"N broken images found"** (from `GET /fallback/scan-broken`),
  shown only when `broken_count > 0`, deep-linking to a filtered view / the sample list.
- Fold broken images into the existing `overallScore` narrative (the computation already lives
  in Dashboard.vue `overallScore`/`overallLabel`) — a lightweight penalty/flag; keep it additive
  so nothing existing breaks.

### F. Settings UI — new page `resources/admin/components/Settings/FallbackSettings.vue`
- New route `/settings/fallback` in `resources/admin/router.js`; add nav entry in `Settings.vue`.
- Controls: master toggle; broken-image toggle + fallback-image picker; featured toggle +
  fallback-image picker + post-type multiselect. Use the existing settings patterns
  (`inject('settingsContext')`, `Api.post('/settings', ...)`, `$t()`), and the WP media modal
  (`wp.media`, already available via `media-views`) for image selection — return attachment ID.
- After save call `clearSettingsCache()`.

### G. Enqueue localize (admin) — `includes/Enqueue.php`
Add the six new `icais_fallback_*` values to the existing `window.icais`
`wp_localize_script('icais-script','icais',[...])` block so the settings UI can read current state.

### H. Free vs Pro split (mirrors the Compression split exactly)

**Free** → master toggle, ONE global fallback image for broken + featured, post-type allow-list,
manual broken-image scan + Dashboard tile. Complete, useful on its own.

**Pro** → (1) **per-post-type featured fallback images**, (2) **scheduled broken scan + email alert**.
As with compression, the free plugin exposes the *hooks and reusable core*; the Pro add-on
(imagecraft-...-pro) registers the value-unlocks ONLY with a valid license — the premium
*behavior* is not in WP.org source.

Free-side unlock points (all in this plan's code):
- `apply_filters('icais_fallback_featured_image_id', $globalId, $postId, $postType)` — applied in
  the `get_post_metadata` callback (and the `post_thumbnail_html` secondary) before use. Free
  passes the global ID straight through; Pro overrides per post type from
  `icais_fallback_featured_map` (mirrors `icais_compression_quality`).
- `FallbackController::scanBrokenCore()` — the scan logic from §D extracted as a **public
  reusable method** (REST `scanBroken()` wraps it), so the Pro cron can call the free core
  (mirrors `GeneratorController::generateMetaForImage`).
- The free plugin registers **NO cron hook** for scanning. The Pro add-on
  (`Features/Fallbacks.php`, license-gated) schedules `icais_fallback_scan` per
  `icais_fallback_scan_schedule`, calls `scanBrokenCore()`, diffs against the previous result
  stored alongside the transient, and `wp_mail()`s the admin only when NEW broken images appear
  (guarded `try/catch (\Throwable)` — cron must never fatal). Mirrors `icais_auto_compress`.

Frontend gating in `FallbackSettings.vue`: per-post-type pickers + schedule/email controls are
Pro-locked via `window.icais.isPro` with the standard upsell treatment (same as
CompressionSettings.vue); the Pro-effect settings keys save normally so a later license
activation picks them up.

### I. Docs — `CLAUDE.md` + `PROJECT_STATE.md`
Per the self-maintenance rule: add the new module to the Dependency Map, the REST table
(`GET /fallback/scan-broken`), the settings keys list, the Vue route/view list, and a dated
`PROJECT_STATE.md` Recent Changes entry.

---

## Backwards-compatibility & fatal-safety checklist
- All new settings default off / empty → **zero behavior change on update**.
- No renamed/removed options, endpoints, response fields, columns. No schema change (no `DB_VERSION` bump).
- New REST field/endpoint is additive; existing responses untouched.
- Frontend class + filters: null-checked, core-WP-only, wrapped where they touch scans (`\Throwable`).
- `get_post_metadata` filter is hot-path: cheap bails first (wrong key / `is_admin()` / feature off),
  static-cached settings, recursion guard — zero measurable overhead when the feature is off.
- New `wp_enqueue_scripts` hook only fires assets when the feature is enabled and a valid image ID exists.
- PHP 7.4 floor respected. Run `/fatal-safety` on the diff before completing (REQUIRED — new hooks + frontend surface).

## Verification (end-to-end)
1. `npm run lint` (fix errors) and, with permission, `npm run prod` to confirm the build.
2. **Featured fallback:** set a fallback image, create a post with no featured image, load an
   archive/single template → the fallback `<img>` renders with correct alt **in a theme that guards
   with `has_post_thumbnail()`** (this is what the `_thumbnail_id` meta filter fixes — test a block
   theme like Twenty Twenty-Four too). In wp-admin: post list "featured image" column / editor
   featured-image panel must NOT show the fallback (the `is_admin()` bail). Real featured images are
   untouched. Deactivate module → reverts (non-destructive, nothing persisted).
3. **Broken fallback:** point an `<img src>` at a deleted upload on a test page → JS swaps it to the
   fallback image and fills alt. Verify BOTH paths: an image that errors *after* page load (capture-phase
   listener) AND one already failed *before* the deferred script ran (initial `naturalWidth === 0`
   sweep — test with cache disabled). Verify no infinite loop when the fallback URL itself 404s,
   and no console errors.
4. **Broken scan:** delete a local upload file on disk, call `GET /fallback/scan-broken` → count/sample
   correct; Dashboard shows the "N broken images found" tile; `?refresh=1` re-scans. On a large
   library (or with the time budget temporarily lowered), confirm the `partial: true` cursor loop
   completes across multiple calls without a PHP timeout.
5. **Off by default:** on a fresh/updated install with the module off, confirm no frontend JS is
   enqueued and no featured/`post_thumbnail_html` change occurs.
6. **Pro split:** with free only — per-post-type map and schedule settings save but have NO effect
   (filter passes global ID through; no cron registered — check `wp cron event list`). With the Pro
   add-on licensed — per-type fallback wins over the global image; `icais_fallback_scan` cron is
   scheduled, and the email fires only on NEW broken images (run twice: second run with no change
   sends nothing).
7. Run `/fatal-safety`, `/review`, `/test` on the diff before commit.
