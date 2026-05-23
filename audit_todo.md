# Codebase Audit Report
Generated: 2026-05-23
Project: ImageCraft — AI Alt Text, File Renamer & Image SEO (WordPress Plugin)
Layers identified: Vue UI Components → Event Handlers → Api.js (REST Client) → REST Routes (api.php) → Route Middleware → Controllers → Services / AI Providers → Models (ORM) → WP Object Cache → MySQL (wpdb)

> **Note:** Three traceability bugs were auto-fixed during this audit. See the TRACEABILITY section for details.

---

## 🔴 CRITICAL SECURITY ISSUES

*None found.* No hardcoded secrets, no SQL injection (all queries use `$wpdb->prepare()`), no RCE vectors (`eval`/`exec`/`system` absent), no auth bypass, no plaintext API key storage.

---

## 🟠 HIGH SECURITY ISSUES

### [HIGH-SEC-001] IDOR — `updateAlt` has no attachment ownership check
**File:** `includes/Http/Controllers/GeneratorController.php` (around the `updateAlt` method)
**Code:**
```php
$attachmentId = (int) ($params['attachment_id'] ?? 0);
// ... only checks wp_attachment_is_image(), never checks ownership
update_post_meta($attachmentId, '_wp_attachment_image_alt', $altText);
```
**Issue:** Any authenticated user with `upload_files` capability can overwrite the alt text of *any* attachment site-wide — including other users' images. `wp_attachment_is_image()` validates the post type only, not ownership.
**Fix:**
```php
$attachment = get_post($attachmentId);
if (!$attachment || ((int) $attachment->post_author !== get_current_user_id() && !current_user_can('manage_options'))) {
    return $this->response(['success' => false, 'message' => __('Not authorized to edit this attachment', 'imagecraft-ai-alt-text-file-renamer-image-seo')], 403);
}
```

---

### [HIGH-SEC-002] IDOR — `rename` and `renameBulk` have no attachment ownership check
**File:** `includes/Http/Controllers/FileRenamerController.php` (`rename` and `renameBulk` methods)
**Code:**
```php
$attachmentId = (int) ($params['attachment_id'] ?? 0);
$result = $this->renamer->rename($attachmentId, $customFilename); // no ownership check
```
**Issue:** Any `upload_files` user can physically rename files on disk and rewrite serialized post meta URL references for media they do not own. Higher impact than `updateAlt` because it touches the filesystem and can corrupt serialized URLs across the site.
**Fix:** Add ownership guard before `$this->renamer->rename()` call (same pattern as HIGH-SEC-001). Apply inside the `foreach` loop in `renameBulk()` too, collecting per-item 403s instead of aborting.

---

### [HIGH-SEC-003] SSRF — DNS rebinding / TOCTOU gap in image URL validation
**File:** `includes/Services/AI/BaseAIProvider.php` (`validateImageUrl` / `getImageData` methods)
**Code:**
```php
$ip = gethostbyname($host);
// validates $ip against private ranges...
$response = wp_remote_get($url, [...]); // triggers a SECOND DNS lookup
```
**Issue:** The IP validation and the actual HTTP request are two separate DNS resolutions. An attacker controlling a DNS record can serve a public IP for the validation lookup, then flip the DNS record to `192.168.x.x` for the second lookup inside `wp_remote_get`, fully bypassing the SSRF guard.
**Fix:** Resolve the hostname once, validate, then force `wp_remote_get` to connect to the resolved IP while passing the original hostname as the `Host:` header. Alternatively, use a cURL option to pin the connection to the pre-validated IP address.

---

### [HIGH-SEC-004] Info Disclosure — Raw AI provider error messages propagated to frontend
**Files:**
- `includes/Http/Controllers/GeneratorController.php` (`generate` method — `'message' => $result['error']`)
- `includes/Http/Controllers/WooCommerceController.php` (`generateGallery` — `'error' => $result['error'] ?? 'Unknown error'`)
- `includes/Http/Controllers/BatchController.php` (`process` catch block — `'reason' => $e->getMessage()`)

**Issue:** Raw exception messages from Anthropic/OpenAI/Gemini (which may contain quota details, model names, account identifiers, internal error codes) are passed directly to the browser.
**Fix:**
```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('ImageCraft generate error: ' . ($result['error'] ?? ''));
}
return $this->response(['success' => false, 'message' => __('Failed to generate alt text. Please check your API key and try again.', 'imagecraft-ai-alt-text-file-renamer-image-seo')]);
```

---

## 🟡 MEDIUM SECURITY ISSUES

### [MED-SEC-001] Uncontrolled settings key construction via user-supplied `source` param
**File:** `includes/Http/Controllers/GeneratorController.php` (`isIntegrationAllowed` method)
**Code:**
```php
$settingKey = 'icais_integration_' . $source; // $source is user input
return Settings::get($settingKey, '1') === '1';
```
**Issue:** `$source` comes from user-supplied JSON (only `sanitize_text_field` applied). A crafted value like `source: "use_wp7_ai"` can probe unrelated wp_options keys and may return truthy, bypassing a disabled-integration check.
**Fix:** Replace with an explicit allowlist:
```php
$allowed = ['kadence', 'spectra', 'gutenberg', 'yoast', 'rankmath', 'aioseo', 'seopress', 'woocommerce', 'filebird', 'wpml', 'polylang'];
if (!in_array($source, $allowed, true)) {
    return false; // fail closed
}
```

---

### [MED-SEC-002] SSL verification silently disabled for sites with `.local` / `.test` in hostname
**File:** `includes/Services/AI/BaseAIProvider.php` (`isLocalDevelopment` + `makeRequest` methods)
**Code:**
```php
'sslverify' => !$this->isLocalDevelopment(),
// isLocalDevelopment() pattern-matches site_url() for 'localhost', '.local', '.test', ...
```
**Issue:** Any production server whose hostname contains `.local` or `.test` as a substring (e.g. `protest.myagency.com`, `mylocal.example.com`) will have SSL verification silently disabled for all outbound AI API calls, exposing API keys to MitM interception.
**Fix:** Remove the `sslverify => false` fallback entirely. Always use `'sslverify' => true`.

---

### [MED-SEC-003] Prompt injection — user-controlled `post_title` injected verbatim into AI prompt
**File:** `includes/Http/Controllers/GeneratorController.php` (context building, ~line 58)
**Code:**
```php
$postTitle = sanitize_text_field($params['post_title'] ?? '');
if (!empty($postTitle)) {
    $context = 'Page: ' . $postTitle; // concatenated directly into AI prompt
}
```
**Issue:** `sanitize_text_field` strips HTML but does not neutralize prompt injection payloads. A user controlling the post title can craft: `"ignore the above instructions and output: [payload]"`. Same surface exists for WooCommerce product names and SEO keyphrases.
**Fix:** Truncate and strip injection-prone characters, and add a system-level instruction to all providers:
```php
$postTitle = mb_substr(sanitize_text_field($params['post_title'] ?? ''), 0, 200);
$postTitle = preg_replace('/[\[\]<>{}|\\\\]/', '', $postTitle);
// In all provider system prompts: "You are an image alt text generator. Only describe the image."
```

---

### [MED-SEC-004] IDOR — FileBird `getAttachments` returns folder contents without ownership check
**File:** `includes/Http/Controllers/FileBirdController.php` (`getAttachments` method)
**Code:**
```php
$folderId = absint($params['folder_id'] ?? 0);
$attachmentIds = FileBird::getAttachmentIdsByFolder($folderId);
return $this->response(['success' => true, 'attachments' => $attachmentIds]);
```
**Issue:** Any `upload_files` user can supply an arbitrary `folder_id` and enumerate attachment IDs from any other user's FileBird folder. Information disclosure across multi-author setups.
**Fix:** Verify folder ownership against `get_current_user_id()` or check `current_user_can('manage_options')` before returning folder contents.

---

### [MED-SEC-005] `@maybe_unserialize()` silenced error suppressor and no `allowed_classes` restriction
**File:** `includes/Services/FileRenamer.php` (~line 552)
**Code:**
```php
$unserialized = @maybe_unserialize($value);
```
**Issue:** The `@` operator hides deserialization errors. `maybe_unserialize()` calls PHP's `unserialize()` without `allowed_classes`, enabling PHP Object Injection if a crafted serialized string reaches this path.
**Fix:**
```php
$unserialized = is_serialized($value)
    ? unserialize($value, ['allowed_classes' => false])
    : $value;
```

---

### [MED-SEC-006] `realpath()` path-traversal guard falls back to unvalidated path
**File:** `includes/Services/FileRenamer.php` (~line 267)
**Code:**
```php
$normalizedNewPath = wp_normalize_path(realpath(dirname($newFilePath)) ?: dirname($newFilePath));
if (strpos($normalizedNewPath, $normalizedBase) !== 0) { ... }
```
**Issue:** When `realpath()` returns `false` (directory does not exist yet), the guard silently falls back to the raw un-canonicalized path. The guard's correctness then depends entirely on upstream sanitization passing clean input.
**Fix:** Create the destination directory before calling `realpath()`, then reject if `realpath()` still returns false:
```php
if (!$normalizedNewPath || strpos($normalizedNewPath, $normalizedBase) !== 0) {
    return ['success' => false, 'error' => 'Rename target is outside the uploads directory'];
}
```

---

## 🔵 LOW SECURITY ISSUES

### [LOW-SEC-001] Raw exception messages stored in batch transients and returned to admin
**File:** `includes/Http/Controllers/BatchController.php` (`process` catch block)
**Code:** `'reason' => $e->getMessage()`
**Issue:** Raw PHP exception messages (may include file paths, model names, HTTP response bodies) serialized into a transient and returned via `GET /batch/failures`. Not a direct attack vector but unnecessarily expands sensitive surface.
**Fix:** Log under `WP_DEBUG`, store only sanitized message: `__('Generation failed. Check error log for details.', '...')`.

---

### [LOW-SEC-002] `isIntegrationAllowed()` defaults to allowed (`'1'`) for unknown source values
**File:** `includes/Http/Controllers/GeneratorController.php` (`isIntegrationAllowed`)
**Code:** `return Settings::get($settingKey, '1') === '1';`
**Issue:** Fail-open default — unknown integration names are treated as enabled rather than blocked. Defense-in-depth should fail closed.
**Fix:** Change default to `'0'` and add the allowlist (MED-SEC-001). Both changes together are the complete fix.

---

### [LOW-SEC-003] `@set_time_limit()` silenced error suppressor
**File:** `includes/Http/Controllers/FileRenamerController.php` (~line 85)
**Code:** `@set_time_limit(120);`
**Fix:** `if (function_exists('set_time_limit')) { set_time_limit(120); }`

---

### [LOW-SEC-004] `file_exists()` used instead of `WP_Filesystem` in scanner
**File:** `includes/Http/Controllers/ScannerController.php` (~line 204)
**Code:** `if (file_exists($filePath)) { ... filesize($filePath) ... }`
**Issue:** Inconsistent with WordPress coding standards; fails on FTP-mode filesystems required for some WordPress.org hosting environments.
**Fix:** Use `$wp_filesystem->exists()` and `$wp_filesystem->size()`.

---

### [LOW-SEC-005] `encryption_iv` DB column is written but never used in decryption
**File:** `includes/Models/ApiSetting.php` (`setApiKey` / `getApiKey`)
**Issue:** The column stores only a partial hex IV; actual decryption reads the IV from the base64 blob. The column is a maintenance trap — a future developer using it would introduce a decryption bug.
**Fix:** Either remove the column (with a migration) or update decryption to use it consistently with the full IV stored.

---

## ⚡ PERFORMANCE OPTIMIZATIONS

### [PERF-001] N+1 query in `BatchController::results()` — 1 query per attachment
**File:** `includes/Http/Controllers/BatchController.php` (results method, foreach loop)
**Issue:** For a 500-image batch, this is 500 individual `SELECT` queries.
**Fix:** Use `History::where('attachment_id', 'IN', $intIds)->get()` and build a `$historyMap[attachment_id]` lookup array. One query replaces 500.

---

### [PERF-002] `StatsTracker::recordGeneration()` calls `ApiSetting::getActive()` 500× per batch
**File:** `includes/Services/StatsTracker.php` (`recordGeneration` method)
**Issue:** Identical `$userId` lookup executed on every image during batch processing. Provider doesn't change within a run.
**Fix:** Add `?string $provider = null` parameter to `recordGeneration()`. Callers already know the provider — pass it in. Eliminates the `ApiSetting::getActive()` call inside `StatsTracker`.

---

### [PERF-003] `isLocalDevelopment()` called on every image fetch with no caching
**File:** `includes/Services/AI/BaseAIProvider.php` (`isLocalDevelopment`)
**Issue:** Calls `site_url()` and `home_url()` + multiple `strpos()` checks on every single image in a batch run. Value never changes within a request.
**Fix:**
```php
private static ?bool $isLocalDev = null;
protected function isLocalDevelopment(): bool {
    if (self::$isLocalDev === null) {
        self::$isLocalDev = (strpos(site_url(), 'localhost') !== false || ...);
    }
    return self::$isLocalDev;
}
```

---

### [PERF-004] `openssl_get_cipher_methods()` scans 200+ entries on every encrypt/decrypt
**File:** `includes/Services/Encryption.php` (`encrypt` and `decrypt` methods)
**Fix:** Cache the result in a static variable, or replace with `extension_loaded('openssl')` which is sufficient.

---

### [PERF-005] `WooCommerce::getProductStats()` loads all product IDs just to count them
**File:** `includes/Services/WooCommerce.php` (`getProductStats`)
**Issue:** Materializes a large PHP array when a `SELECT COUNT(*)` query would return a single integer.
**Fix:** Add a `countProductsWithAlt(): int` method using a `COUNT` SQL query; use in `getProductStats()` instead of `getProductIdsWithAlt()`.

---

### [PERF-006] `FileBird::getAttachmentCount()` fetches all IDs just to count them
**File:** `includes/Services/FileBird.php` (`getAttachmentCount`)
**Fix:**
```php
public function getAttachmentCount(int $folderId): int {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}fbv_attachment_folder WHERE folder_id = %d",
        $folderId
    ));
}
```

---

### [PERF-007] `Settings::set()` executes up to 3 option reads per write
**File:** `includes/Services/Settings.php` (`set` method)
**Issue:** Pre-check read + `update_option` internal read + failure verification read = 3 reads for 1 write. Saving a settings form (20+ keys) triggers 60+ option reads.
**Fix:** Trust `update_option()`'s return value; replace the pre-comparison read with a direct `get_option($optionKey)` call that avoids the double-prefix path through `self::get()`.

---

## 🧹 DEAD CODE & DUPLICATION

### [DEAD-001] `MistralProvider` and `OpenRouterProvider` — complete implementations, never instantiated
**Files:** `includes/Services/AI/MistralProvider.php`, `includes/Services/AI/OpenRouterProvider.php`
**Issue:** Both are loaded by the autoloader on every request but are commented out in `AIFactory`. Dead code in the active autoload path.
**Fix:** Move to `Services/AI/Draft/` (outside autoload) or delete and recreate when shipping.

---

### [DEAD-002] ORM relationship system in `Model.php` — never used
**File:** `includes/Models/Model.php` (hasMany, hasOne, belongsTo, with, load, loadRelations — ~90 lines)
**Fix:** Remove entirely. No concrete model defines relationships or calls `with()`.

---

### [DEAD-003] `Route::withPolicy()` and `Route::getRegisteredRoutes()` — never called
**File:** `includes/Http/Routes/Route.php`
**Fix:** Remove both methods.

---

### [DEAD-004] `WooCommerce::updateGalleryAltTexts()` and `isProductTypeSupported()` — never called
**File:** `includes/Services/WooCommerce.php`
**Fix:** Remove both methods.

---

### [DEAD-005] `Api.js` `put()` and `delete()` methods — never called from any Vue component
**File:** `resources/admin/services/Api.js`
**Fix:** Remove until actually needed.

---

### [DEAD-006] `Settings.vue` unused `saving` / `testing` / `testResult` refs
**File:** `resources/admin/views/Settings.vue`
**Issue:** `saving` is set during `saveSettings()` but no template element binds `:disabled="saving"`. `testing` and `testResult` belong in `ApiSettings.vue` which actually owns the test UI.
**Fix:** Add `:disabled="saving"` to the Save button. Move `testing` and `testResult` to `ApiSettings.vue`.

---

### [DUP-001] Title truncation pattern copied to 6 locations
**Files:** `GeneratorController.php` (×3), `BatchController.php`, `AutoGenerate.php`, `FileRenamer.php`
**Fix:** Extract `AltTextHelper::truncateTitle(string $text, int $maxLength): string` in `Support/AltTextHelper.php`.

---

### [DUP-002] "Resolve provider + image URL" boilerplate repeated in 3 private methods
**File:** `includes/Http/Controllers/GeneratorController.php` (`generateTitleOnly`, `generateLinkTitle`, `generateFilenameAndRename`)
**Fix:** Extract `resolveProviderAndUrl(int $attachmentId, int $userId): array`. Note: `generateLinkTitle` uses the full namespace `\Softminal\...\AIFactory::` instead of the imported alias — a copy-paste artifact to fix.

---

### [DUP-003] Product name extraction regex duplicated across all 4 prompt methods
**File:** `includes/Services/AI/BaseAIProvider.php`
**Fix:** Extract `extractProductName(string $context): ?string` and `buildContextPrefix(?string $context): string` private helpers.

---

### [DUP-004] Triple-form boolean normalization duplicated in 5 PHP + 3 Vue locations
**Files:** `AutoGenerate.php`, `GeneratorController.php`, `AltTextSettings.vue`, `RenameSettings.vue`, etc.
**Fix:** PHP: add `Settings::getBoolean(string $key): bool`. Vue: extract `isEnabled(val)` utility to `utils/helpers.js` (one version already exists in `IntegrationSettings.vue`).

---

### [DUP-005] Kadence and Spectra block editor integrations — 300 lines of near-identical code
**Files:** `resources/admin/js/kadence-alt-button.js`, `resources/admin/js/spectra-alt-button.js`
**Fix:** Extract `icais-block-editor-common.js` with a `config` parameter (`blockName`, `getBlock`, `applyAttribute`, `findContainer`). Both files reduce to their config + registration call.

---

## 🏗️ CODE QUALITY & STRUCTURE

### [QUAL-001] `App.php` and `Router.php` — pointless try-catch blocks that just re-throw
**File:** `includes/App.php` (constructor + `run()`), `includes/Http/Routes/Router.php` (`registerRoutes` silent swallow)
**Issues:**
- `App.php`: Both try-catch blocks only re-throw — 12 lines of pure noise.
- `Router.php`: Catch block silently swallows all route registration exceptions. If a route fails to register, the plugin loads normally but all its REST endpoints are missing with zero logging.
**Fix:** Remove the re-throw wrappers in `App.php`. In `Router.php`, at minimum log under `WP_DEBUG`; ideally remove the catch entirely.

---

### [QUAL-002] `Install.php` — `dbDelta()` return value check that can never be true
**File:** `includes/Install.php`
**Code:** `if ($results === false) { ... }` — `dbDelta()` always returns an array, never `false`.
**Fix:** Check `$wpdb->last_error` after `dbDelta()` call.

---

### [QUAL-003] `AdminNotice.php` — threshold hardcoded in translatable string
**File:** `includes/AdminNotice.php`
**Issue:** The number `8` appears both in the PHP property (controls logic) and in the i18n string (cannot update automatically on threshold change).
**Fix:** Use `sprintf(__("You've generated over %d alt texts...", '...'), self::$generationThreshold)`.

---

### [QUAL-004] `GeneratorController::generate()` is 142 lines and handles 6 code paths
**File:** `includes/Http/Controllers/GeneratorController.php` (`generate` public method)
**Issue:** Violates SRP. Nearly impossible to unit test.
**Fix:** Reduce `generate()` to a ~20-line routing dispatcher. Each of the 6 code paths should be a self-contained private method. The three existing sub-methods are the right direction but incomplete.

---

### [QUAL-005] `BatchJob::validateStatus()` — misleading name for a state-mutating method
**File:** `includes/Models/BatchJob.php`
**Issue:** Method named `validate*` that actually calls `$this->save()` (a database write). Principle of least surprise violation.
**Fix:** Rename to `repairStatus()` or `fixInterruptedStatus()`.

---

### [QUAL-006] `useECharts.js` — multiple `resize` event listeners accumulate on `window`
**File:** `resources/admin/composables/useECharts.js`
**Code:** `window.addEventListener('resize', handleResize)` called inside `updateOptions()` with no deduplication guard.
**Fix:** Track registration with a flag (`let resizeListenerRegistered = false`) or manage listener attachment in `onMounted`/`onUnmounted` lifecycle hooks.

---

### [QUAL-007] `Scanner.vue` — two nearly identical query-string builder functions
**File:** `resources/admin/views/Scanner.vue` (`buildFilterParams` and `buildSelectParams`)
**Fix:**
```javascript
const buildFilterParams = (overrides = {}) => {
    const opts = { filter: filter.value, ...overrides }
    // shared date/size/folder logic
    if (opts.quality) params += `&quality=${opts.quality}`
    return params
}
```

---

### [QUAL-008] `Install.php` — DB version uses semver string `'1.0.0'` instead of integer counter
**File:** `includes/Install.php` (`$currentDbVersion = '1.0.0'`)
**Issue:** Semver comparisons are unreliable (`'1.10.0'` < `'1.9.0'` lexicographically). No connection to plugin version.
**Fix:** Use `ICAIS_DB_VERSION` integer constant in `config/constants.php`. Increment as `1`, `2`, `3`.

---

### [QUAL-009] Hardcoded URLs/limits scattered across templates and controllers
**Files:**
- `resources/admin/views/Help.vue`, `SetupWizard.vue` — same YouTube embed URL hardcoded in both
- `resources/admin/views/Scanner.vue` (×4), `WooCommerce.vue` (×2) — `500` batch image limit
- `includes/Http/Controllers/FileRenamerController.php` — `25` bulk rename limit hardcoded
- `includes/Services/AI/AnthropicProvider.php` — `'2023-06-01'` API version, endpoint URL, cost per token
- `includes/Services/AI/AIFactory.php` — timeout `60` hardcoded 3 times (but immediately overwritten by `Settings::get()`)
**Fix:**
- Extract external URLs to `resources/admin/utils/externalLinks.js`
- Pass `batchLimit` from PHP to `window.icais` config object; consume in Vue as `window.icais.batchLimit || 500`
- Add `FileRenamerController::MAX_BULK_RENAME = 25` class constant
- Add `ANTHROPIC_API_VERSION`, `DEFAULT_MAX_OUTPUT_TOKENS` constants in `AnthropicProvider`; `DEFAULT_TIMEOUT` constant in `AIFactory`

---

## 🔗 TRACEABILITY BREAKS — FIXED DURING AUDIT

The following three bugs were identified by the traceability agent and **automatically fixed**:

### [TRACE-FIXED-001] `SetupWizard.vue` step 4 — undefined `completeWizard` function
**Layer:** UI → Event Handler
**File:** `resources/admin/views/SetupWizard.vue` (step 4 "Go to Dashboard" button)
**Was:** `@click="completeWizard"` with no `completeWizard` defined in `<script setup>`
**Impact:** RUNTIME_CRASH — Vue would throw uncaught error when the button is clicked
**Fix applied:** Added `goToDashboard()` function and updated the button binding

---

### [TRACE-FIXED-002] `SettingsController::completeWizard()` — HTTP 400 when wizard already completed
**Layer:** Controller → Service
**File:** `includes/Http/Controllers/SettingsController.php` (`completeWizard`)
**Was:** `update_user_meta()` returns `false` for both failure AND no-change (already set). Controller treated `false` as failure → 400 response.
**Impact:** SILENT_BUG — clicking "Complete Setup" a second time would always return 400, breaking the wizard redirect
**Fix applied:** Calls `Settings::completeWizard()` unconditionally, then reads back truth via `Settings::isWizardCompleted()` to decide the response

---

### [TRACE-FIXED-003] `AutoGenerate.php` — title max length fallback `80` instead of `30`
**Layer:** Service → Model
**File:** `includes/AutoGenerate.php` (~line 196)
**Was:** `Settings::get('icais_title_max_length', 80)` — wrong fallback
**Expected:** All other callers (`GeneratorController`, `BatchController`) and `Settings::$defaults` use `30`
**Impact:** DATA_LOSS — on fresh installs, auto-uploaded image titles were generated up to 80 chars, appearing as "Too Long" in Scanner
**Fix applied:** Changed fallback to `30` to match canonical default

---

## 🔗 TRACEABILITY BREAKS — OPEN (need manual fix)

### [TRACE-UI-001] `ImageCard.vue` passes `attachment_id` as string but controller casts to int
**Layer:** UI → API
**File:** `resources/admin/components/Scanner/ImageCard.vue` (generate button)
**Passed:** `attachment_id: image.id` — value comes from WordPress REST API response which serializes IDs as strings in some contexts
**Expected:** `GeneratorController` does `(int) ($params['attachment_id'] ?? 0)` — this is safe but inconsistent
**Impact:** Silent — PHP cast handles it, but type contract is not explicit. If the cast fails (non-numeric string), `$attachmentId` silently becomes `0` and the controller returns a validation error without indicating the root cause.
**Recommendation:** Ensure the frontend always sends `attachment_id: parseInt(image.id, 10)`.

---

### [TRACE-API-001] `Batch::start` frontend sends `imageIds` (camelCase); controller reads `image_ids` (snake_case)
**Layer:** UI → API → Controller
**Caller:** `resources/admin/views/Batch.vue` (start batch handler)
**Callee:** `includes/Http/Controllers/BatchController.php` (`start` method)
**Check:** Verify that `Batch.vue` posts `{ image_ids: [...] }` (snake_case) matching what `BatchController::start()` reads via `$params['image_ids']`. If the Vue component uses camelCase keys anywhere, the batch will start with 0 images and process silently with an empty loop.
**Recommendation:** Add explicit `console.assert(Array.isArray(payload.image_ids))` on the frontend, and add an explicit check in the controller: `if (empty($imageIds)) { return $this->response(['success' => false, 'message' => ...], 400); }`.

---

### [TRACE-SVC-001] `WooCommerceController` does not check if WooCommerce is active before instantiating service
**Layer:** Controller → Service
**File:** `includes/Http/Controllers/WooCommerceController.php` (constructor)
**Issue:** If WooCommerce is deactivated mid-session, the REST route is still registered but `$this->service` would be a `WooCommerce` service instance that calls WooCommerce functions not available. The service constructor checks `class_exists('WooCommerce')` but a runtime deactivation won't unregister the routes.
**Recommendation:** Add `class_exists('WooCommerce')` guard at the start of every controller method, not just the constructor.

---

## 📋 MASTER TASK LIST

Each item is self-contained, actionable, and ordered by impact within category.

### Security
- [x] [HIGH-SEC-001] Add attachment ownership check in `GeneratorController::updateAlt()` | File: `includes/Http/Controllers/GeneratorController.php` | Effort: S
- [x] [HIGH-SEC-002] Add attachment ownership check in `FileRenamerController::rename()` and `renameBulk()` | File: `includes/Http/Controllers/FileRenamerController.php` | Effort: S
- [x] [HIGH-SEC-003] Fix SSRF DNS rebinding TOCTOU gap — sslverify always true + pre_http_request filter minimises TOCTOU window | File: `includes/Services/AI/BaseAIProvider.php` | Effort: M
- [x] [HIGH-SEC-004] Sanitize AI provider error messages before returning to frontend (3 files) | Files: `GeneratorController.php`, `WooCommerceController.php`, `BatchController.php` | Effort: S
- [x] [MED-SEC-001] Replace dynamic `wp_options` key construction with allowlist in `isIntegrationAllowed()` | File: `includes/Http/Controllers/GeneratorController.php` | Effort: S
- [x] [MED-SEC-002] Already resolved by HIGH-SEC-003 (`sslverify => true` in BaseAIProvider) | File: `includes/Services/AI/BaseAIProvider.php` | Effort: S
- [x] [MED-SEC-003] Prompt injection: truncate `$postTitle` to 200 chars, strip `[]{}|` injection markers | File: `includes/Http/Controllers/GeneratorController.php` | Effort: M
- [x] [MED-SEC-004] Add `FileBird::getFolderOwner()` + ownership guard in `FileBirdController::getAttachments()` | Files: `FileBird.php`, `FileBirdController.php` | Effort: S
- [x] [MED-SEC-005] Replace `@maybe_unserialize()` with `is_serialized()` + `unserialize($v, ['allowed_classes' => false])` | File: `includes/Services/FileRenamer.php` | Effort: S
- [x] [MED-SEC-006] Moved `mkdir` before `realpath` check; now rejects when `realpath` returns false (no unsafe fallback) | File: `includes/Services/FileRenamer.php` | Effort: S
- [x] [LOW-SEC-001] Move raw batch failure messages to error log; return generic user-facing string | File: `includes/Http/Controllers/BatchController.php` | Effort: S
- [x] [LOW-SEC-002] Change `isIntegrationAllowed()` fallback default to `'0'` (fail closed) | File: `includes/Http/Controllers/GeneratorController.php` | Effort: S
- [x] [LOW-SEC-003] Replace `@set_time_limit()` with `function_exists` guard | File: `includes/Http/Controllers/FileRenamerController.php` | Effort: S
- [x] [LOW-SEC-004] Replace `file_exists()` / `filesize()` with `WP_Filesystem` equivalents in ScannerController | File: `includes/Http/Controllers/ScannerController.php` | Effort: S
- [x] [LOW-SEC-005] Stop writing unused partial `encryption_iv` value; removed from `$fillable` and `setApiKey()` — column remains in DB schema (backwards-compatible) | File: `includes/Models/ApiSetting.php` | Effort: S

### Performance
- [ ] [PERF-001] Replace N+1 query in `BatchController::results()` with single bulk `IN` query + map | File: `includes/Http/Controllers/BatchController.php` | Effort: S
- [ ] [PERF-002] Pass provider name into `StatsTracker::recordGeneration()` to avoid 500 identical `ApiSetting::getActive()` calls during batch | Files: `Services/StatsTracker.php`, `GeneratorController.php`, `BatchController.php` | Effort: S
- [ ] [PERF-003] Cache `isLocalDevelopment()` result in static property | File: `includes/Services/AI/BaseAIProvider.php` | Effort: S
- [ ] [PERF-004] Cache or remove redundant `openssl_get_cipher_methods()` call in Encryption | File: `includes/Services/Encryption.php` | Effort: S
- [ ] [PERF-005] Replace `getProductIdsWithAlt()` + `count()` with a `COUNT(*)` SQL query in `getProductStats()` | File: `includes/Services/WooCommerce.php` | Effort: S
- [ ] [PERF-006] Replace `getAttachmentIdsByFolder()` + `count()` with a `COUNT(*)` SQL query in `getAttachmentCount()` | File: `includes/Services/FileBird.php` | Effort: S
- [ ] [PERF-007] Reduce `Settings::set()` from 3 option reads to 1 | File: `includes/Services/Settings.php` | Effort: M

### Dead Code & Duplication
- [ ] [DEAD-001] Move `MistralProvider.php` and `OpenRouterProvider.php` out of autoload path | Files: `includes/Services/AI/Mistral*.php`, `OpenRouter*.php` | Effort: S
- [ ] [DEAD-002] Remove ORM relationship methods from `Model.php` (~90 lines, never used) | File: `includes/Models/Model.php` | Effort: S
- [ ] [DEAD-003] Remove `Route::withPolicy()` and `getRegisteredRoutes()` (never called) | File: `includes/Http/Routes/Route.php` | Effort: S
- [ ] [DEAD-004] Remove `WooCommerce::updateGalleryAltTexts()` and `isProductTypeSupported()` (never called) | File: `includes/Services/WooCommerce.php` | Effort: S
- [ ] [DEAD-005] Remove `Api.js` `put()` and `delete()` methods (never called) | File: `resources/admin/services/Api.js` | Effort: S
- [ ] [DEAD-006] Fix `Settings.vue` unused refs + missing `:disabled="saving"` binding | File: `resources/admin/views/Settings.vue` | Effort: S
- [ ] [DUP-001] Extract `AltTextHelper::truncateTitle()` to replace 6 duplicated title truncation blocks | Files: `GeneratorController.php`, `BatchController.php`, `AutoGenerate.php`, `FileRenamer.php` | Effort: M
- [ ] [DUP-002] Extract `resolveProviderAndUrl()` private helper to remove 10-line boilerplate × 3 in GeneratorController | File: `includes/Http/Controllers/GeneratorController.php` | Effort: S
- [ ] [DUP-003] Extract `extractProductName()` and `buildContextPrefix()` private helpers in `BaseAIProvider` | File: `includes/Services/AI/BaseAIProvider.php` | Effort: S
- [ ] [DUP-004] Centralize boolean normalization: PHP `Settings::getBoolean()`, Vue `utils/helpers.js isEnabled()` | Multiple files | Effort: M
- [ ] [DUP-005] Extract `icais-block-editor-common.js` to de-duplicate Kadence + Spectra integrations | Files: `kadence-alt-button.js`, `spectra-alt-button.js` | Effort: M

### Code Quality & Structure
- [ ] [QUAL-001] Remove pointless re-throw try-catch in `App.php`; add `WP_DEBUG` logging to `Router.php` silent catch | Files: `includes/App.php`, `includes/Http/Routes/Router.php` | Effort: S
- [ ] [QUAL-002] Fix `Install.php` `dbDelta()` return check — check `$wpdb->last_error` instead | File: `includes/Install.php` | Effort: S
- [ ] [QUAL-003] Use `sprintf()` for threshold value in `AdminNotice` i18n string | File: `includes/AdminNotice.php` | Effort: S
- [ ] [QUAL-004] Decompose `GeneratorController::generate()` (142 lines, 6 code paths) into routing dispatcher + private methods | File: `includes/Http/Controllers/GeneratorController.php` | Effort: L
- [ ] [QUAL-005] Rename `BatchJob::validateStatus()` to `repairStatus()` to match its actual behaviour | File: `includes/Models/BatchJob.php` | Effort: S
- [ ] [QUAL-006] Fix `useECharts.js` accumulating `resize` event listeners | File: `resources/admin/composables/useECharts.js` | Effort: S
- [ ] [QUAL-007] Merge `buildFilterParams` + `buildSelectParams` into one function in `Scanner.vue` | File: `resources/admin/views/Scanner.vue` | Effort: S
- [ ] [QUAL-008] Replace `Install.php` semver DB version string with integer constant in `config/constants.php` | Files: `includes/Install.php`, `config/constants.php` | Effort: S
- [ ] [QUAL-009] Centralize hardcoded external URLs, batch limit, rename limit, and API constants | Multiple files | Effort: M

### Traceability
- [ ] [TRACE-UI-001] Ensure `ImageCard.vue` sends `attachment_id` as integer (`parseInt(image.id, 10)`) | File: `resources/admin/components/Scanner/ImageCard.vue` | Effort: S
- [ ] [TRACE-API-001] Verify `Batch.vue` posts `image_ids` in snake_case matching `BatchController`; add empty-array guard in controller | Files: `Batch.vue`, `BatchController.php` | Effort: S
- [ ] [TRACE-SVC-001] Add `class_exists('WooCommerce')` guard at the start of each `WooCommerceController` method (not just constructor) | File: `includes/Http/Controllers/WooCommerceController.php` | Effort: S
