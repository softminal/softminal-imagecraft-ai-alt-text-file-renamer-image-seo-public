# ImageCraft Audit Report

> Generated: 2026-02-24
> Covers: Security, Optimization, and Traceability (6 layers)

---

## 1. Security

### Critical

- **S-C1: Dev mode enabled in production** — `IMGCRAFT_DEV_MODE` is `true` in `config/constants.php`. This loads assets from `localhost:4000` in production and may expose debug info.
  - File: `config/constants.php`
  - Fix: Now auto-detects via `.dev` sentinel file. Build script ensures `.dev` is excluded from distribution ZIP.
- **S-C2: Gemini API key exposed in URL** — `GeminiProvider` embeds the API key as a query parameter (`?key=`). This leaks the key in server logs, proxy logs, and browser history.
  - File: `includes/Services/AI/GeminiProvider.php`
  - Fix: Switched to `x-goog-api-key` header. API key no longer appears in URLs.
- **S-C3: SSRF via image URL** — `BaseAIProvider::getImageData()` fetches arbitrary URLs via `wp_remote_get()`. An attacker with upload permissions could point to internal network resources (`http://169.254.169.254/` etc.).
  - File: `includes/Services/AI/BaseAIProvider.php`
  - Fix: Added `validateImageUrl()` — enforces http(s) scheme, resolves hostname, rejects private/reserved IPs via `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`. Same-site URLs are allowed.

### High

- **S-H1: SSL verification disabled** — `BaseAIProvider::makeRequest()` sets `sslverify => false`, allowing MitM attacks on API calls.
  - File: `includes/Services/AI/BaseAIProvider.php`
  - Fix: Already set to `sslverify => true` in `makeRequest()`. Image fetches use `sslverify => !isLocalDevelopment()` for dev compat.
- **S-H2: Missing ownership check on API settings** — `SettingsController::saveApiKey()` uses `get_current_user_id()` but doesn't verify the user owns the API setting being modified (e.g., when updating an existing record).
  - File: `includes/Http/Controllers/SettingsController.php`
  - Fix: Added explicit `(int) $apiSetting->user_id !== $userId` check before modifying existing records. Returns 403 on mismatch.
- **S-H3: DoS via large batch size** — No server-side cap on `icais_batch_size`. A user could set it to millions, causing memory exhaustion.
  - File: `includes/Http/Controllers/SettingsController.php`
  - Fix: Added server-side `$intCaps` in `update()`: batch_size capped at 50, request_timeout at 300s, max_length and rename_max_length at 500. Also cast timeout to `(int)` in AIFactory.
- **S-H4: Information disclosure in error messages** — Multiple controllers expose raw exception messages when `WP_DEBUG` is true, which may leak file paths, DB structure, or API internals.
  - File: `includes/Http/Controllers/SettingsController.php`
  - Fix: All 3 catch blocks in SettingsController now log to `error_log()` (gated behind WP_DEBUG with phpcs ignore) and return generic user-facing messages only.
- **S-H5: Weak encryption key derivation** — `Encryption.php` uses `AUTH_KEY` and `SECURE_AUTH_KEY` directly as encryption key/IV seed. These may be the WordPress defaults on poorly configured sites.
  - File: `includes/Services/Encryption.php`
  - Fix: Added check for default WordPress salt placeholder (`'put your unique phrase here'`). Throws exception with instructions to generate unique salts. Kept existing SHA-256 derivation for backward compat with existing encrypted keys.
- **S-H6: Unprotected Encryption::decrypt() in AIFactory** — `AIFactory::getProviderForUser()` calls `Encryption::decrypt($apiSetting->api_key_encrypted)` directly instead of using the model's `getApiKey()` method, bypassing any future access controls.
  - File: `includes/Services/AI/AIFactory.php`
  - Fix: Now uses `$apiSetting->getApiKey()` which wraps decryption in try-catch and returns null on failure. Removed unused `Encryption` import.

### Medium

- **S-M1: No rate limiting on API test endpoint** — `POST /settings/test-api` has no rate limit. An attacker could use it to rapidly validate stolen API keys.
  - File: `includes/Http/Controllers/SettingsController.php`
  - Fix: Added transient-based rate limit — max 5 requests per minute per user. Returns HTTP 429 when exceeded.
- **S-M2: API key logged in sanitize_text_field** — `saveApiKey()` and `testApi()` pass the raw API key through `sanitize_text_field()`. While not a direct log, some WAFs/plugins log sanitization calls.
  - File: `includes/Http/Controllers/SettingsController.php`
  - Fix: Replaced `sanitize_text_field(wp_unslash(...))` with just `wp_unslash()` + length validation for API key fields in both `saveApiKey()` and `testApi()`.
- **S-M3: No CSRF token on wizard complete** — `POST /settings/wizard/complete` relies only on WordPress nonce from the REST API but has no additional CSRF protection.
  - File: `includes/Http/Routes/Route.php`
  - Fix: Already handled. Route `permission_callback` checks `is_user_logged_in()` + capability. WP REST API cookie auth verifies `X-WP-Nonce` automatically. Also fixed global catch in Route to log errors instead of exposing them.
- **S-M4: File renamer path traversal risk** — If attachment metadata contains manipulated filenames, the renamer could write outside expected directories.
  - File: `includes/Services/FileRenamer.php`
  - Fix: Added `strpos($normalizedNewPath, $normalizedBase)` check in `performRename()` — rejects any rename target outside `wp_upload_dir()['basedir']`.
- **S-M5: No model validation against allowed list** — `testApi()` and `AIFactory::create()` accept any model string from the user. A malicious model string could be passed to external APIs.
  - File: `includes/Services/AI/AIFactory.php`
  - Fix: Added `in_array($model, $class::getAvailableModels(), true)` check in `create()`. Invalid models silently fall back to provider default.
- **S-M6: Image data kept in memory** — `getImageData()` loads entire image into memory as base64 (33% larger). Large images could cause OOM.
  - File: `includes/Services/AI/BaseAIProvider.php`
  - Fix: Added 20 MB max file size check before base64 encoding. Throws exception with clear message.
- **S-M7: Batch processing without timeout guard** — Batch process endpoint can run indefinitely if API calls hang.
  - File: `includes/Http/Controllers/BatchController.php`
  - Fix: Added wall-clock time guard in processing loop. Checks elapsed time before each image and bails out when `$maxSeconds` (2x request timeout, min 30s) is reached.
- **S-M8: No nonce verification documented for REST routes** — Verify all REST routes use `wp_rest` nonce or cookie authentication.
  - File: `includes/Http/Routes/Route.php`
  - Fix: Verified. All routes go through `handleMiddleware()` which checks `is_user_logged_in()` + `current_user_can()`. WP REST API handles nonce via `X-WP-Nonce` header. Global catch now logs errors instead of exposing them via WP_DEBUG.

### Low

- **S-L1: Missing Content-Type validation on image fetch** — `getImageData()` trusts the MIME type from `wp_check_filetype()` without verifying actual file content.
  - File: `includes/Services/AI/BaseAIProvider.php`
  - Fix: Added `detectMimeFromContent()` that validates magic bytes (JPEG, PNG, GIF, WebP). MIME type is now determined from actual content, not URL extension. Rejects non-image files.
- **S-L2: Hardcoded timeout of 60 seconds** — Default timeout is 60s. Combined with batch processing, this could keep connections open very long.
  - File: `includes/Services/AI/BaseAIProvider.php`
  - Fix: Already addressed by S-H3 — timeout is read from settings (`icais_request_timeout`) and capped at 300s server-side. Batch processing has a wall-clock time guard (S-M7).
- **S-L3: No audit logging** — API key changes, provider switches, and bulk operations are not logged for audit trail.
  - Files: `SettingsController.php`
  - Fix: Added `do_action('icais_api_key_saved', $userId, $provider)` hook after successful API key save. Admins/security plugins can hook into this for audit logging.
- **S-L4: Plugin version not checked on settings page** — No version mismatch warning if DB schema is outdated.
  - File: `includes/Http/Controllers/SettingsController.php`
  - Fix: Added `'plugin_version' => ICAIS_VERSION` to the settings `index()` response. Frontend can now display and check version.
- **S-L5: OpenRouter HTTP-Referer header** — Sends `site_url()` as referer, leaking the site URL to OpenRouter.
  - File: `includes/Services/AI/OpenRouterProvider.php`
  - Fix: Replaced `site_url()` with a static WordPress.org plugin URL. No longer leaks the user's site URL.
- **S-L6: No key rotation mechanism** — Users cannot rotate API keys without deleting and re-adding.
  - File: `includes/Models/ApiSetting.php`
  - Fix: Already addressed — `saveApiKey()` finds existing records for the same user/provider and calls `setApiKey()` to overwrite. This is effectively key rotation. The `icais_api_key_saved` hook (S-L3) now fires on rotation too.
- **S-L7: SVG in supported MIME types** — SVGs can contain scripts. While sent to AI APIs (not rendered), they're listed as supported.
  - File: `includes/Services/AI/BaseAIProvider.php`
  - Fix: Removed `'svg' => 'image/svg+xml'` from `getMimeType()`. Additionally, `detectMimeFromContent()` (S-L1) only recognizes JPEG, PNG, GIF, and WebP magic bytes, so SVGs are rejected by content validation too.
- **S-L8: Cache poisoning risk** — WP object cache is used for model data. If cache is shared across users, one user's data could be served to another.
  - File: `includes/Models/Model.php`
  - Fix: Changed cache group from `'icais'` to `'icais_models'` for better namespacing. Cache keys already include the full SQL (with user_id in WHERE clauses), so cross-user data leakage was not practically exploitable, but the more specific group reduces collision risk with other plugin caches.

---

## 2. Optimization

### Dead Code

- **O-DC1: RequestSanitizer class unused** — `includes/Http/RequestSanitizer.php` is never instantiated or called anywhere.
  - Fix: Deleted `includes/Http/Sanitizer/RequestSanitizer.php` and its directory.
- **O-DC2: Relationship traits unused** — `HasMany`, `HasOne`, `BelongsTo` in `includes/Models/Relations/` are defined but never used by any model.
  - Fix: Deleted all 3 relation classes and the `Relations/` directory. The `Model` base class implements these relationships directly as methods.
- **O-DC3: wpMediaBridge.js unused** — `resources/admin/utils/wpMediaBridge.js` is never imported.
  - Fix: Deleted the file.
- **O-DC4: Unused Route methods** — `Route::put()`, `Route::delete()`, `Route::resource()`, `Route::getWithId()`, `Route::postWithId()` are defined but never used in `api.php`.
  - Fix: Removed all 5 unused methods from `Route.php`. Only `get()` and `post()` remain.
- **O-DC5: Commented-out providers** — Mistral and OpenRouter are commented out in `AIFactory::$providerClasses` but their PHP files and UI references remain.
  - Fix: Intentionally kept commented out — only Anthropic, OpenAI, and Gemini are supported in this release. Mistral and OpenRouter will be enabled in a future release. Provider files retained for forward compatibility.

### Duplication

- **O-DU1: OpenAI-compatible provider duplication** — `OpenAIProvider`, `MistralProvider`, and `OpenRouterProvider` share nearly identical Chat Completions API code (request building, response parsing, headers).
  - Fix: Extracted `ChatCompletionsProvider` abstract base class with shared `generateAltText()`, `testConnection()`, `extractAltText()`, and `calculateCost()`. Each provider now only defines endpoint, headers, cost rate, models, and optionally a test model override. ~270 lines reduced to ~160 total.
- **O-DU2: normalizeForComparison duplicated** — `SettingsController::normalizeForComparison()` duplicates logic that could live in `Settings` service.
  - Fix: Made `Settings::normalizeValueForComparison()` public. `SettingsController` now calls it directly. Removed the private duplicate.
- **O-DU3: Image count queries duplicated** — Dashboard stats and Scanner both count images with missing alt text using separate queries.
  - Fix: Added `AltTextHelper::getImageCounts()` returning `['total' => int, 'missing' => int]`. Both `StatsController` and `ScannerController` now use this shared method.

### Performance

- **O-P1: N+1 queries in BatchController::results()** — Loads up to 500 History records, then for each calls `wp_get_attachment_url()` (which may query the DB).
  - Fix: Added `_prime_post_caches($intIds, true, true)` before the loop to batch-load all post and meta data in a single query. Subsequent `get_post()`, `wp_get_attachment_url()`, etc. are served from cache.
- **O-P2: N+1 in StatsController** — Dashboard stats loads all History rows into memory just to count by status.
  - Fix: Replaced `History::where('user_id', $userId)->get()` + PHP counting loop with a single `SELECT status, COUNT(*) ... GROUP BY status` query. Zero rows loaded into memory.
- **O-P3: All History loaded into memory** — `History::where(...)` loads all matching records. For sites with thousands of images, this could use excessive memory.
  - Fix: Addressed together with O-P2. The dashboard no longer loads History rows — it uses an aggregated COUNT query. Other usages (recent activity) already use pagination via `History::getRecent()`.
- **O-P4: Settings::all() called multiple times per request** — Each call to `Settings::all()` reads all options from the database.
  - Fix: Added `private static $allCache` property. `all()` returns cached result on subsequent calls within the same request. Cache is busted in `set()` when any setting changes.

### Architecture

- **O-A1: Manual autoloading** — The plugin uses a custom autoloader instead of Composer's optimized classmap.
  - Fix: Already addressed — project uses Composer PSR-4 autoloading (`vendor/autoload.php`). No manual `includes/autoload.php` exists.
- **O-A2: Static method overuse** — `Settings`, `AIFactory`, `Encryption` are entirely static, making testing and mocking difficult.
  - Fix: Acknowledged. This is a deep architectural concern that would require significant restructuring with regression risk. Deferred as a future improvement; the current static approach works correctly and is consistent across the codebase.
- **O-A3: BatchController creates GeneratorController** — `BatchController::process()` instantiates `GeneratorController` directly, creating tight coupling.
  - Fix: Acknowledged. Extracting a generation service is a significant refactor that would touch multiple controllers and risk regressions. Deferred as a future improvement. The coupling is contained to one method.
- **O-A4: $timeout type mismatch** — `Settings::get('icais_request_timeout')` returns a string, but `BaseAIProvider` constructor expects `int`. PHP will coerce, but it's fragile.
  - Fix: Already addressed in security fixes — `AIFactory::create()` explicitly casts `(int) Settings::get('icais_request_timeout', $timeout)`.

---

## 3. Traceability

### Layer 1: Vue Frontend → API Client

- **T-L1-1: Router guard field path discrepancy** — Router guard checks `result.wizard_completed` but the API returns it nested under `result.data.wizard_completed` (via the Controller response wrapper). May cause wizard redirect loop on some setups.
  - Files: `resources/admin/router.js`, `includes/Http/Controllers/SettingsController.php`
  - Fix: Verified — no issue. `Controller::response()` returns `WP_REST_Response` which serializes the data directly. `Api.get()` parses the JSON, so `settings.wizard_completed` correctly accesses the top-level key. No wrapper exists.
- **T-L1-2: WooCommerce attachment_id fallback** — Scanner.vue sends `item.id` (the post/product ID) as `attachment_id` when `item.featured_image` is missing. The backend expects an attachment ID, not a post ID.
  - File: `resources/admin/views/Scanner.vue`
  - Fix: Verified — no issue. Scanner.vue images come from `ScannerController::scan()` which queries `post_type = 'attachment'`. All `image.id` values are valid attachment IDs, never post/product IDs.
- **T-L1-3: Capability mismatch on batch start** — Scanner.vue sends batch start requests, but the batch endpoint may require `manage_options` while Scanner only requires `upload_files`.
  - Files: `resources/admin/views/Scanner.vue`, `includes/Http/api.php`
  - Fix: By design. Batch operations (processing hundreds of images) intentionally require `manage_options` for admin-level access control. Scanner viewing requires only `upload_files`. Non-admin users get a clear 403 error from the REST API. The separation is intentional security design.
- **T-L1-4: Missing response error handling** — Several API calls in Vue components don't handle non-success responses (e.g., `saveSettings()` in Settings.vue doesn't check `result.success`).
  - Files: Various Vue components
  - Fix: Added `else` branches to `saveSettings()` and `saveApiKey()` in Settings.vue to show `result.message` on non-success responses. Scanner.vue already had adequate error handling.

### Layer 2: REST Routes → Controllers

- **T-L2-1: CLAUDE.md namespace incorrect** — Documentation says `/wp-json/imagecraft/v1` but actual namespace may be `icais/v1`.
  - File: `CLAUDE.md`, `config/constants.php`
  - Fix: Updated CLAUDE.md — namespace corrected to `icais/v1`, table prefix to `icais_`, constant names to `ICAIS_*`, PHP namespace to `Softminal\ImageCraft\`.
- **T-L2-2: 8+ undocumented REST routes** — Several routes registered in `api.php` are not listed in the CLAUDE.md route table (e.g., renamer endpoints, WooCommerce endpoints).
  - Files: `CLAUDE.md`, `includes/Http/api.php`
  - Fix: Updated CLAUDE.md route table to list all 27 endpoints across 7 route groups (settings, scanner, generator, batch, stats, renamer, notice, woocommerce) with capability requirements.
- **T-L2-3: Parameter name mismatch in renamer** — Frontend sends one parameter name but the controller expects a different one for rename preview.
  - Files: Renamer Vue component, `RenamerController.php`
  - Fix: Updated `FileRenamerController::preview()` to accept both `attachment_id` and `id` via `$request->get_params()` for backwards compatibility.

### Layer 3: Controllers → Services

- **T-L3-1: Unchecked save() return values** — Multiple controllers call `$model->save()` without checking the return value, silently dropping failures.
  - Files: `GeneratorController.php`, `BatchController.php`, `StatsController.php`
  - Fix: Added return value checks on critical `$history->save()` calls in `GeneratorController::accept()` and `GeneratorController::reject()`, returning 500 error on failure. Non-critical saves (stats tracking) remain unchecked as they are best-effort.
- **T-L3-2: Inconsistent timeout defaults** — `AIFactory::create()` defaults to 60, `AIFactory::getProviderForUser()` also defaults to 60, but `Settings` default for `icais_request_timeout` may differ.
  - Files: `includes/Services/AI/AIFactory.php`, `includes/Services/Settings.php`
  - Fix: Already consistent — `Settings::$defaults['icais_request_timeout'] = 60` matches the `AIFactory::create()` parameter default. `create()` always reads from Settings as the single source of truth via `Settings::get('icais_request_timeout', $timeout)`. Added clarifying comment.
- **T-L3-3: Multiple unchecked Encryption::decrypt()** — If decryption fails (corrupted data, changed salts), it may return garbage or throw. Callers don't handle this.
  - File: `includes/Services/AI/AIFactory.php`
  - Fix: Already addressed in security pass (S-H6). `ApiSetting::getApiKey()` wraps `Encryption::decrypt()` in try-catch and returns null on failure. `AIFactory::getProviderForUser()` checks for null.

### Layer 4: Services → AI Providers

- **T-L4-1: Anthropic missing null coalesce on input_tokens** — If the API response doesn't include `usage.input_tokens`, it will generate a PHP notice.
  - File: `includes/Services/AI/AnthropicProvider.php`
  - Fix: Added `?? 0` fallback: `($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0)`.
- **T-L4-2: Mistral testConnection uses hardcoded model** — `testConnection()` in MistralProvider uses a local `$model` variable or constant instead of `$this->model`.
  - File: `includes/Services/AI/MistralProvider.php`
  - Fix: Already addressed in optimization pass (O-DU1). `ChatCompletionsProvider` base class provides `testConnection()` using `$this->getTestModel()` which defaults to `$this->model`.
- **T-L4-3: Header format inconsistency** — Some providers use string headers (`'Authorization: Bearer ...'`), others use associative arrays (`['Authorization' => 'Bearer ...']`). `makeRequest()` in BaseAIProvider must handle both.
  - Files: All provider files
  - Fix: Already addressed. `BaseAIProvider::makeRequest()` normalizes both header formats (string `'Key: value'` and associative `['Key' => 'value']`) into the associative array format required by `wp_remote_post()`. All ChatCompletionsProvider subclasses use associative arrays; Anthropic uses string format which is normalized.
- **T-L4-4: Flat-rate cost calculation** — All providers use a single `COST_PER_1K_TOKENS` constant regardless of which model is selected, making cost tracking inaccurate.
  - Files: All provider files
  - Fix: Documented as intentionally approximate. Added docblock comment on `BaseAIProvider::calculateCost()` clarifying this is for dashboard display purposes. Per-model cost maps would add complexity and require frequent updates as providers change pricing.

### Layer 5: Models → Database

- **T-L5-1: N+1 in BatchController::results()** — Loads batch history records then queries attachment data individually for each.
  - File: `includes/Http/Controllers/BatchController.php`
  - Fix: Already addressed in optimization pass (O-P1). Added `_prime_post_caches($intIds, true, true)` before the results loop.
- **T-L5-2: Race conditions on Stats/BatchJob counters** — `Stats::increment()` and `BatchJob` counter updates read-then-write without locking, causing lost updates under concurrency.
  - Files: `includes/Models/Stats.php`, `includes/Models/BatchJob.php`
  - Fix: Rewrote both `Stats::increment()` and added `BatchJob::increment()` to use atomic SQL: `UPDATE table SET field = field + amount WHERE id = %d`. Updated `BatchController::process()` and `StatsTracker::recordGeneration()` to use atomic increments instead of `+=` and non-atomic cost assignment.
- **T-L5-3: Cache invalidation bypass in saveApiKey** — `SettingsController::saveApiKey()` uses direct `$wpdb->update()` to deactivate other providers, bypassing model cache invalidation.
  - File: `includes/Http/Controllers/SettingsController.php`
  - Fix: Added `ApiSetting::invalidateCache()` call after the direct `$wpdb->update()`. Made `Model::bumpCacheVersion()` protected (from private) so child models can expose cache invalidation.
- **T-L5-4: LIKE query false positives** — `GeneratorController` uses `LIKE '%term%'` queries that can match unintended records.
  - File: `includes/Http/Controllers/GeneratorController.php`
  - Fix: Added post-verification for the `_product_image_gallery` LIKE query. After the SQL LIKE narrows candidates, the code now parses the comma-separated gallery IDs and verifies exact match via `in_array($attachmentId, $galleryIds, true)`.

### Layer 6: Settings End-to-End

- **T-L6-1: icais_rename_max_length does nothing** — The Settings UI has a slider for rename max length, but `FileRenamer` uses a hardcoded constant `IMGCRAFT_RENAME_MAX_LENGTH` instead of reading the setting.
  - Files: `resources/admin/views/Settings.vue`, `includes/Services/FileRenamer.php`
  - Fix: Updated `FileRenamer::generateFilename()` to read `Settings::get('icais_rename_max_length', 120)` instead of the hardcoded constant. Renamed constant to `DEFAULT_MAX_FILENAME_LENGTH` as fallback only.
- **T-L6-2: icais_email_on_complete is orphaned** — The setting exists in defaults and the UI but no code ever reads it to send an email.
  - Files: `includes/Services/Settings.php`, `resources/admin/views/Settings.vue`
  - Fix: Removed `icais_email_on_complete` from `Settings::$defaults` and from `SettingsController::update()` boolKeys validation. The setting was not referenced in any Vue component.
- **T-L6-3: OpenRouter/Mistral half-disabled** — Providers are commented out in `AIFactory::$providerClasses` but still appear in the SetupWizard UI. Users can select them, enter a key, and get an "Unknown provider" error.
  - Files: `includes/Services/AI/AIFactory.php`, `resources/admin/views/SetupWizard.vue`
  - Fix: Intentionally kept disabled per user request. Mistral and OpenRouter are planned for next release. The SetupWizard only shows Anthropic, OpenAI, and Gemini as selectable providers.
- **T-L6-4: Double-prefixed wp_options keys** — Settings keys like `icais_api_provider` may be stored with an additional plugin prefix in `wp_options`, making them hard to find in the database.
  - File: `includes/Services/Settings.php`
  - Fix: Documented the double-prefix behavior in `Settings::get()` docblock. The stored key format is `ICAIS_TABLE_PREFIX + key` (e.g. `icais_icais_max_length`). Changing this would break backwards compatibility with existing installations. All reads and writes are consistent.
- **T-L6-5: No model validation on save** — When a user saves a model selection, the value is not validated against `getAvailableModels()`. A tampered request could set an invalid model string.
  - File: `includes/Http/Controllers/SettingsController.php`
  - Fix: Added model validation in `SettingsController::update()`. Model setting keys (`icais_openai_model`, `icais_anthropic_model`, `icais_gemini_model`) are validated against `AIFactory::getAvailableModels()`. Invalid values are silently dropped.

---

## Priority Matrix


| Priority     | Count  | Items                            |
| ------------ | ------ | -------------------------------- |
| Critical     | 3      | S-C1, S-C2, S-C3                 |
| High         | 6      | S-H1 through S-H6                |
| Medium       | 8      | S-M1 through S-M8                |
| Low          | 8      | S-L1 through S-L8                |
| Optimization | 14     | O-DC1–5, O-DU1–3, O-P1–4, O-A1–4 |
| Traceability | 19     | T-L1-1 through T-L6-5            |
| **Total**    | **58** |                                  |


## Suggested Fix Order

1. **Critical security first**: S-C1 (dev mode), S-C3 (SSRF), S-C2 (Gemini key)
2. **High security**: S-H1 (SSL), S-H6 (decrypt bypass), S-H2 (ownership), S-H3 (DoS)
3. **Broken features**: T-L6-3 (half-disabled providers), T-L6-1 (rename slider), T-L6-2 (orphaned email)
4. **Data integrity**: T-L5-2 (race conditions), T-L5-3 (cache bypass), T-L3-1 (unchecked saves)
5. **Performance**: O-P1 (N+1 batch), O-P3 (memory), O-P4 (settings caching)
6. **Code quality**: Dead code removal, duplication reduction, architecture improvements
7. **Documentation**: T-L2-1 (namespace), T-L2-2 (undocumented routes)

