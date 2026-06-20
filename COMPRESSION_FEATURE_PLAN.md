# Image Compression Feature Plan — "ImageCraft Compress"

> Status: **✅ Implemented (v1)** — 2026-06-20 · Engine: **Local (`wp_get_image_editor`)** · Backup: **Always, with Restore** · Monetization: **Freemium (20/day free, Pro unlimited + auto + WebP-future)**
>
> Shipped: `Services/ImageCompressor.php`, `Models/Compression.php`,
> `Http/Controllers/CompressorController.php`, `includes/AutoCompress.php`,
> `views/Compression.vue`, `components/Settings/CompressionSettings.vue`; batch
> `type=compress`; `icais_compressions` table (db_version 1.1.0). WebP format
> conversion deferred (would change URLs). See PROJECT_STATE.md → Recent Changes.

## Context

ImageCraft today generates AI alt text and renames files. **Image compression is the
highest-SEO-impact adjacent feature** — large unoptimized images are the #1 cause of slow
LCP/PageSpeed scores, which is exactly this plugin's audience.

**Decisions locked in:**
- **Engine:** Local only — WordPress' built-in `wp_get_image_editor()` (GD/Imagick). No API
  key, no per-image cost, works offline. Ships fastest, fits the plugin's free ethos.
- **Backup:** Always back up the original before compressing, with a "Restore Original"
  action. Compression rewrites files in place on live sites — this is non-negotiable safety.
- **Scope/UX:** Designed below (PM-recommended focused v1).

This is the **first** image-manipulation feature in the plugin (no existing GD/Imagick usage),
so it's greenfield — but it MUST follow the plugin's fatal-safety and backwards-compatibility
rules (live plugin, real users on WordPress.org).

---

## Recommended v1 — the user journey

A new top-level **"Compress"** page in the SPA, plus a settings sub-page. Four capabilities,
all reusing existing infrastructure:

1. **Bulk compress (core).** Scan the media library, show each image's current size + a
   savings estimate and a library-wide "≈ X MB recoverable" headline. User selects images →
   **Compress Selected** → runs through the **existing Batch engine** (progress bar, chunking,
   heartbeat, resume) → results summary: "Saved 42 MB (38%) across 120 images."
2. **Single-image compress.** A per-image **Compress** button in the grid (mirrors the
   existing "Rename File" button), with instant before/after size + a savings badge.
3. **Auto-compress on upload (opt-in, default OFF).** New uploads are compressed in the
   background via cron, mirroring `AutoGenerate`.
4. **Settings + safety.** Quality slider, optional max-width downscale, optional WebP
   conversion, always-backup (forced on), skip-already-compressed, min-size threshold.
   Every compressed image gets a **Restore Original** action.

**Defaults (everything opt-in for backwards-compat):**
`compression_enabled = 0`, `auto_compress = 0`, `quality = 80`, `max_width = 2560`,
`convert_webp = 0` (off — some themes/CDNs mishandle it), `keep_backup = 1` (forced),
`skip_compressed = 1`, `min_size_kb = 50`. Existing users see zero change until they opt in.

---

## Architecture — reuse-first

Mirrors the **File Renamer** (in-place file rewrite + thumbnail regen) and **Alt-text Batch**
(scan → select → batch) features almost exactly. Key simplification vs. renaming:
**compression keeps filenames, so URLs don't change → no `post_content`/postmeta rewriting.**

### Backend (PHP)

- **`includes/Services/ImageCompressor.php`** (new) — core service modeled on
  `Services/FileRenamer.php`:
  - `compress(int $attachmentId, array $opts = []): array` → `['success', 'original_size',
    'new_size', 'saved_bytes', 'saved_percent', ...]`
  - `restore(int $attachmentId): array`, `preview(int $attachmentId): array`
  - Per-image flow:
    1. Guard: `wp_attachment_is_image()`, file exists, not already compressed (unless forced).
    2. **Back up** full-size + every generated size to `uploads/icais-originals/<y>/<m>/...`
       via `WP_Filesystem` (reuse `FileRenamer::getFilesystem()`).
    3. Compress: `wp_get_image_editor($file)` → `set_quality($q)` → optional `resize($maxW)` →
       optional WebP `save($file,'image/webp')` else `save()`. Guard `WP_Error` return.
    4. Regenerate thumbnails via `wp_generate_attachment_metadata()` +
       `wp_update_attachment_metadata()`.
    5. Record to `Compression` model + `_icais_compressed` / `_icais_compression_stats` meta.
  - **Fatal-safety:** `function_exists('wp_get_image_editor')` guard; wrap all editor/filesystem
    work in `try/catch (\Throwable)`; never throw to caller.

- **`includes/Models/Compression.php`** (new) — extends `Model` like `Models/History.php`.
  Fillable: `attachment_id, user_id, original_size, compressed_size, saved_bytes,
  saved_percent, method, backup_path, status, error_message, created_at`.

- **`includes/Install.php`** — add `icais_compressions` table to `createTables()` (indexes on
  `attachment_id`, `user_id`, `status`, `created_at`); **version-gated migration** (bump
  `icais_db_version`, create on upgrade for existing installs); add to uninstall `DROP TABLE`.

- **`includes/Http/Controllers/CompressorController.php`** (new) — mirrors
  `FileRenamerController`: `scan`, `compress`, `restore`, `preview`, `stats`. Same
  sanitize-at-top, ownership check, and `$this->response()` shape.

- **Batch reuse** — extend the existing engine via the `icais_batch_type_<jobId>` transient /
  `$isTitleOnly` switch in `BatchController::process()`. Add a `type === 'compress'` branch
  calling `ImageCompressor::compress()`. Reuse start/process/status/results/cancel/resume.

- **`includes/AutoCompress.php`** (new) — mirrors `includes/AutoGenerate.php`: `add_attachment`
  → check `icais_auto_compress` → `wp_schedule_single_event(time()+5,'icais_auto_compress',
  [$id,$userId])`. Register in `App.php` `$loadOrder`.

- **`includes/Services/Settings.php`** — add the new keys to `$defaults`.

- **`includes/Http/api.php`** — new group:
  ```php
  Route::prefix('compressor')->capability('upload_files')->group(function () {
      Route::get('/scan',      [CompressorController::class, 'scan']);
      Route::post('/compress', [CompressorController::class, 'compress']);
      Route::post('/restore',  [CompressorController::class, 'restore']);
      Route::get('/preview',   [CompressorController::class, 'preview']);
      Route::get('/stats',     [CompressorController::class, 'stats']);
  });
  ```
  (Bulk goes through existing `batch/*` routes with `type: 'compress'`.)

### Frontend (Vue)

- **`resources/admin/views/Compression.vue`** (new) — scan grid + bulk select, modeled on
  `Scanner.vue`; "≈ X MB recoverable" headline, filters (All / Large / Uncompressed), per-card
  size + savings badge + single Compress/Restore, **Compress Selected** → starts a
  `type:'compress'` batch and routes to existing `Batch.vue`.
- **`resources/admin/components/Settings/CompressionSettings.vue`** (new) — toggles + quality
  slider + max-width + WebP toggle, bound via `inject('settingsContext')`.
- **`resources/admin/router.js`** — add `/compression` route + `settings/compression` child
  (must pass the wizard guard).
- **`includes/Menu.php` / SPA nav** — add a "Compress" entry.
- **Dashboard** — "Storage saved" stat card fed by `compressor/stats`.

### Docs (mandatory per repo rules)
Update **CLAUDE.md** (REST table, PHP/Frontend structure, Dependency Map, Database Tables,
Settings keys, Dev Notes) and **PROJECT_STATE.md** (sections + dated Recent Changes entry).

---

## Critical files

| Action | File |
|---|---|
| New core service | `includes/Services/ImageCompressor.php` |
| New model | `includes/Models/Compression.php` |
| New controller | `includes/Http/Controllers/CompressorController.php` |
| New upload hook | `includes/AutoCompress.php` |
| New table + migration | `includes/Install.php` |
| Batch type branch | `includes/Http/Controllers/BatchController.php` (~`:300`) |
| Settings defaults | `includes/Services/Settings.php` (`$defaults`) |
| Routes | `includes/Http/api.php` |
| Load order | `includes/App.php` |
| New views | `resources/admin/views/Compression.vue`, `components/Settings/CompressionSettings.vue` |
| Router/menu | `resources/admin/router.js`, `includes/Menu.php` |
| Reuse patterns | `includes/Services/FileRenamer.php`, `includes/AutoGenerate.php`, `includes/Models/History.php` |

---

## Backwards-compatibility & fatal-safety checklist

- All new settings opt-in; existing installs unchanged until enabled.
- New table via version-gated migration (fresh installs + upgrades); never rename/remove
  existing options, columns, or REST fields (additive only).
- `wp_get_image_editor()` may return `WP_Error`; `function_exists()`-guard it; catch
  `\Throwable` around all editor/filesystem work; never throw to hook/REST callers.
- Path safety on backup/restore: `wp_normalize_path()` + `realpath()` + confirm paths stay
  inside `wp_upload_dir()` (reuse FileRenamer traversal guards).
- Backup BEFORE any destructive write; restore copies the backup back + regenerates metadata.
- PHP 7.4 floor; no 8.0-only syntax. Run `/fatal-safety` on the diff (REQUIRED).

---

## Verification

1. `npm run lint` + `npm run prod` pass; `/test` for PHP syntax/structure.
2. Manual end-to-end:
   - Upload large JPEG → single Compress → file shrinks, renders at all sizes,
     `icais-originals/` backup exists, stats row recorded.
   - **Restore Original** → file/thumbnails revert, flag clears.
   - Bulk: scan → select all → Compress Selected → Batch completes, MB-saved summary;
     cancel/resume mid-run works.
   - Toggle Auto-compress on → upload → compressed via cron shortly after.
   - Deactivate/reactivate → migration runs once; alt-text/rename/batch unaffected.
3. `/fatal-safety` and `/review` on the diff.

---

## Out of scope for v1 (future)
External BYOK compressors (TinyPNG/ShortPixel) — architect the service so a provider factory
(mirroring `AIFactory`) can drop in later, but ship local-only now. Bulk WebP `<picture>`/CDN
serving and EXIF-strip options are deferred.
