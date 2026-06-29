# Plan: "Generate All" — Combined Alt + Title + Caption (one click)

## Context

ImageCraft already generates image **alt text**, **title**, and **filename** individually.
The user wants a single **"Generate All"** action that produces **alt text + title +
caption** for an image in **one click** (and in bulk), powered by **one combined AI
call**. This adds the missing image-SEO field (caption → `post_excerpt`), which Yoast/
Rank Math then consume for image schema/OG — so we complement existing SEO plugins
rather than emitting competing `<meta>` tags ourselves (no front-end output layer).

Confirmed decisions:
1. **One combined AI call** returning strict JSON `{alt_text, title, caption}` (≈⅓ cost, 3× faster).
2. Counts as **1 generation** against the daily limit.
3. Fields **individually toggleable**, all ON by default.
4. **Auto-generate-on-upload is PRO-ONLY** (mirrors auto-compress); free = manual single + bulk.

This is a **live WP.org plugin** → every change is additive, opt-in, and fatal-safe.
Existing single Alt/Title paths are left **untouched** (no refactor — avoids regressions).

---

## 1. AI provider — `includes/Services/AI/BaseAIProvider.php`
Mirror the existing `setTitleOnlyMode`/`getTitleOnlyPrompt` pattern:
- Add `protected $metaMode = false;` + `setMetaMode($enabled = true): self`.
- In `getPrompt()`, add a branch (resetting the flag, like the others):
  `if ($this->metaMode) { $this->metaMode = false; return $this->getMetaPrompt($context); }`
- New `getMetaPrompt(?string $context)`: instruct the model to return **only strict minified
  JSON** with exactly keys `alt_text`, `title`, `caption` (disabled fields → empty string),
  embedding per-field char caps: `icais_max_length` (125), `icais_title_max_length` (30),
  new `icais_caption_max_length` (150). Reuse existing context/keyphrase/language handling.
- **No provider-subclass changes** — Anthropic/OpenAI/Gemini/WP7 all route through
  `generateAltText()→getPrompt()→extractAltText()`; the JSON returns in the usual `alt_text` slot
  and the controller parses it.

## 2. Controller — `includes/Http/Controllers/GeneratorController.php`
- **Reusable core** `public function generateMetaForImage(int $attachmentId, ?int $userId=null, ?string $context=null, bool $forceAccept=false): array`
  — modeled on `generateAltText()` (lines 164-266). Steps: validate + `wp_attachment_is_image`;
  `UsageLimiter::canGenerate`; `AIFactory::getProviderForUser` (null-guard); `wp_get_attachment_url`
  (false-guard); `setMetaMode(true)` + `generateAltText(...)` inside the existing image-fetch
  `try/catch`; **parse JSON defensively** (strip ``` fences, `json_decode`, on failure fall back to
  raw text as `alt_text`); apply per-field toggles + per-field max length + per-field skip-existing;
  `UsageLimiter::recordUsage` **once**; record **one** `StatsTracker::recordGeneration` row
  (alt as `generated_alt`); preview-on → return fields + `history_id` (save nothing);
  preview-off / `$forceAccept` → save each enabled field (see §4).
- **REST** `generateMeta($request)` — mirror `generate()` param/context assembly (lines 25-86,
  reuse `isIntegrationAllowed`, Woo/page/SEO context), enforce non-admin ownership like `updateAlt`
  (lines 664-671), then call the core and `return $this->response($result)`.
- **REST** `acceptMeta($request)` — model on `accept()` (lines 271-336): load `History::find`,
  verify ownership, save edited alt/title/caption, set history `accepted`, `StatsTracker::recordAccept`.

## 3. Routes — `includes/Http/api.php`
Inside the existing `generator` group (already `upload_files` + `RoleGate`, lines 38-44):
```php
Route::post('/generate-meta', [GeneratorController::class, 'generateMeta']);
Route::post('/accept-meta',   [GeneratorController::class, 'acceptMeta']);
```

## 4. Caption persistence
- Save to **`post_excerpt`** via `wp_update_post(['ID'=>$id,'post_excerpt'=>sanitize_text_field($caption)], true)`
  and check `is_wp_error()` (guard, never throw).
- Add tracking postmeta **`_icais_generated_caption`** (mirrors `_icais_generated_title`) for
  skip-existing + preview re-display. `post_excerpt` remains source of truth.
- Alt → `update_post_meta(_wp_attachment_image_alt)`; Title → `_icais_generated_title` postmeta +
  `$wpdb->update($wpdb->posts, ['post_title'=>…])` + `clean_post_cache` (mirror lines 496-501).

## 5. History / Stats — **no schema change**
One history row per combined generation (alt in existing `generated_alt`; title/caption tracked via
their postmeta, as title is today). Counters increment once. No `Install.php`/`db_version` migration.

## 6. Settings — `includes/Services/Settings.php` (+ `SettingsController.php`)
Add to `$defaults` (additive; all resolve via `Settings::get` defaults → zero migration):
```php
'icais_meta_enabled'          => '0',  // feature master toggle OFF — existing users unaffected
'icais_meta_include_alt'      => '1',  // when used, all three ON by default
'icais_meta_include_title'    => '1',
'icais_meta_include_caption'  => '1',
'icais_meta_auto_generate'    => '0',  // auto-on-upload (effect is Pro-gated; free never acts)
'icais_caption_max_length'    => 150,
'icais_caption_default_tone'  => 'professional',
'icais_caption_skip_existing' => '0',
```
In `SettingsController`: add the int key (`icais_caption_max_length`, with a cap) to `$intKeys`/`$intCaps`
and the booleans to `$boolKeys`. `update()` only persists keys in `$defaults`, so this is sufficient.

## 7. Batch (`type: 'meta'`) — `includes/Http/Controllers/BatchController.php`
- `start()`: accept `'meta'` in the batch-type whitelist (stored in `icais_batch_type_<id>` transient).
- `process()`: add `$isMeta` branch calling `generateMetaForImage(..., $forceAccept=true)`, building
  `$context` like the alt branch, wrapped in `try/catch (\Throwable)` (like the compress branch).
- `status()/results()/resume()`: extend the `batch_type` label passthrough to include `'meta'`.
- Batch routes stay `manage_options` → **bulk meta is admin-only**.

## 8. Auto-on-upload — PRO-ONLY (auto-compress precedent)
- **Free plugin registers NO `add_attachment` hook for meta.** `generateMeta...` core is `public` and
  self-contained so the **Pro add-on** can call it directly (no duplicated logic).
- The setting `icais_meta_auto_generate` exists (so UI can store it / Pro can read it), but free never
  acts on it. The Pro add-on registers its own guarded `add_attachment` handler (license + `icais_meta_enabled`
  + `Permission::isAllowed` + excluded-types + skip-existing) that calls the free core — exactly like
  auto-compress lives in the Pro add-on, not free `AutoGenerate.php`.

## 9. Frontend — `resources/admin/`
- **`views/Scanner.vue`**: per-card **"Generate All"** button (shown when `settings.icais_meta_enabled==='1'`;
  per-image, allowed for non-admins) → `Api.post('/generator/generate-meta', {...})`. Combined preview
  with 3 editable fields (each shown if its `icais_meta_include_*` is on) → **"Accept All"** →
  `Api.post('/generator/accept-meta', {...})`. Bulk "Generate All" stays inside the `v-if="isAdmin"`
  bar → `Api.post('/batch/start', {type:'meta', ids})`.
- **`views/Batch.vue`**: add a "meta"/"All Metadata" type label only (logic already generic).
- **New `components/Settings/MetaSettings.vue`** + child route `'/settings/meta'` in `router.js` + nav
  entry in `views/Settings.vue`. Uses `inject('settingsContext')`, `$t()`, `clearSettingsCache()` on save.
  Controls: master toggle, 3 field toggles, caption tone/length/skip-existing, and a **Pro-locked**
  auto-on-upload toggle gated by `window.icais.isPro` (reuse `CompressionSettings.vue` upsell pattern).
- **`includes/Services/TranslationStrings.php`**: add new strings ("Generate All", "Caption",
  "Accept All", "All Metadata", caption labels, Pro upsell).

## 10. Fatal-safety checklist (run `/fatal-safety` on the diff — REQUIRED)
JSON parse in `try/catch (\Throwable)` with raw-text fallback + string coercion; AI call inside existing
`try/catch`; batch branch `try/catch (\Throwable)`; null-guard provider/url/History/get_post;
`wp_update_post` checked with `is_wp_error`; **no new free hooks**; PHP 7.4 floor (no enums/readonly/union/
`str_contains`); Woo context stays behind existing `function_exists`/`isActive` guards.

## 11. Docs
- **CLAUDE.md**: REST table (+`/generator/generate-meta`, `/generator/accept-meta`); batch `type` (+`meta`);
  Dependency Map (`generateMetaForImage` reused by Generator/Batch/Pro; `setMetaMode/getMetaPrompt`);
  new `icais_meta_*`/`icais_caption_*` keys (+ `icais_meta_enabled` opt-in); Pro-split paragraph;
  Frontend (`MetaSettings.vue`, Scanner "Generate All").
- **PROJECT_STATE.md**: update features/map/flows + prepend dated Recent Changes entry.

---

## Verification (manual, end-to-end)
1. Enable `icais_meta_enabled`; confirm 3 toggles default on; toggle caption off → field disappears.
2. Single Generate All (preview on): one API call fills 3 fields; edit + Accept All → `_wp_attachment_image_alt`,
   `post_title`, `post_excerpt` all updated; **one** History row; usage counter **+1**.
3. Preview off → auto-saves immediately.
4. Skip-existing on alt → alt skipped, title/caption still generate; still counts as 1.
5. Usage = 1: 51st generate-all on free → 429.
6. Bulk batch `type:'meta'` (admin) → all 3 saved per image; status shows meta label; `\Throwable` safe.
7. Non-admin permitted role: per-image works; bulk meta UI hidden + `/batch` blocked.
8. Pro lock: `!isPro` → auto toggle disabled w/ upsell + upload triggers nothing; Pro+licensed → upload
   auto-generates via Pro `add_attachment` calling the free core.
9. Malformed AI response → graceful fallback (alt=raw text), no white screen.
10. `npm run lint` (and `npm run prod` only with explicit permission).

## Critical files
- `includes/Services/AI/BaseAIProvider.php`
- `includes/Http/Controllers/GeneratorController.php`
- `includes/Http/Controllers/BatchController.php`
- `includes/Services/Settings.php` (+ `Http/Controllers/SettingsController.php`, `Http/api.php`)
- `resources/admin/views/Scanner.vue` (+ new `components/Settings/MetaSettings.vue`, `router.js`, `views/Settings.vue`, `views/Batch.vue`)
- `includes/Services/TranslationStrings.php`
- Docs: `CLAUDE.md`, `PROJECT_STATE.md`
