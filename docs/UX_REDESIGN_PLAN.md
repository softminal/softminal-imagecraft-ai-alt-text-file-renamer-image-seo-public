# ImageCraft — Premium UX Redesign + Next-Gen Formats (ONE release)

## Context

ImageCraft grew feature-by-feature and **feels messy**. This project ships, in **one
single release**, both (a) a full premium UX redesign that brings the plugin up to the
organization bar of the user's own **`softminal-b2b-wholesale-for-woocommerce`** plugin,
and (b) a new **WebP/AVIF conversion** feature that lives in the redesigned structure.

**"Premium" = the _feel_, not the Pro tier** (fewer, quieter nudges — not more upsell).

### Decisions locked with the user
- **Two-pillar mental model:** Image SEO + Performance.
- **Adopt the B2B organizing patterns, keep Tailwind** (do NOT migrate to Element Plus).
- **Do NOT copy the B2B plugin's visual design.** The two plugins have totally
  different use cases and purposes. The B2B plugin contributes ONLY its organizing
  *principles* (the 5 mechanisms below); ImageCraft gets its **own visual identity** —
  its own brand color/palette, its own layout personality, its own component styling.
  No copied theme, no copied dashboard layout, no same-looking UI. "Same bar of
  organization, different design."
- **One release:** the phases below are a *build order*, not separate ships. Everything
  lands together in a single version bump. Backwards-compatible throughout (live on
  WordPress.org).
- **WebP/AVIF serving:** sidecar + `<picture>` — keep the original, generate
  `.webp`/`.avif` beside it, rewrite on the frontend with fallback. Non-destructive,
  reversible, no broken URLs.
- **WebP/AVIF Free vs Pro:** Free = manual WebP (single + bulk) + frontend delivery;
  Pro = AVIF + auto-convert-on-upload (mirrors the compression split).

### The B2B bar — 5 mechanisms that make it feel premium
(Ref: `…/softminal-b2b-wholesale-for-woocommerce/resources/admin/` — **reference for
STRUCTURE only, never for visual design**: don't reuse its colors, button shapes,
card styling, or layout compositions.)
1. **One token file** (`styles/tokens.css`) — single color/radius/type system + dark
   mode, applied globally. *(ImageCraft has none → the "unpolished" feel.)*
2. **One navigation model** (`config/navigation.js`) driving top nav, contextual
   sub-bar, settings sidebar, dashboard quick-actions, Pro pills — labels can't drift.
3. **"Daily-workflows vs set-and-forget" rule** → keeps the top nav to ~5 items.
4. **Descriptions everywhere + a contextual sub-bar** (`SectionBar.vue`).
5. **Uniform state handling** — one page-head, one card, layout-mirroring skeletons, one
   empty-state, one error toast.

---

## Target model (two pillars, B2B-style)

```
Dashboard            ← calm overview: one health story per pillar + quick actions
Image SEO            ← alt · title · caption ("Generate All") · file rename · WooCommerce
Performance          ← compress · Next-gen formats (WebP/AVIF) · image fallbacks · broken-scan
[WooCommerce]        ← admin + woo_active (daily workflow → stays top-level)
Settings             ← set-and-forget, grouped under the same pillars + General/Account
Help
```

---

## Build order (all phases ship together as ONE release)

### Phase 1 — Design system foundation on Tailwind (mechanisms 1 & 5)
- **New `resources/admin/design/tokens.css`** — CSS vars (`--ic-*`): brand, surfaces,
  text, success/warn/danger/info pairs, radius + type scale, **two pillar accents**
  (SEO vs Performance), optional `:root.dark` block. **ImageCraft's OWN identity, not
  the B2B palette:** grow it from the plugin's existing blue accent (`blue-600`/
  `blue-100` family already used in nav/buttons) so the redesign feels like ImageCraft
  matured — not like a different product, and not like the B2B plugin.
- **Bridge into Tailwind v4 (CSS-first — there is NO `tailwind.config.js`):** add a
  `@theme` block in the existing entry `resources/scss/index.css` (which already has
  `@import "tailwindcss"` + `@source`) mapping tokens to Tailwind namespaces
  (`--color-brand`, `--radius-card`, `--font-sans`, pillar accents) so v4
  auto-generates utilities (`bg-brand`, `text-pillar-seo`, …) directly from tokens.
- **New `@ui/` primitives (Tailwind):** `UiCard`, `UiButton`, `UiBadge`, `UiField`,
  `UiSectionHeader`, `UiEmptyState`, `UiSkeleton`, `UiPageHead`. **`@ui/` already
  exists** (`Checkbox.vue`, `Switch.vue`, `Pagination.vue`, `notification.vue`,
  `index.js` barrel) — reuse `Switch.vue` as the toggle (restyle with tokens, don't
  duplicate), extend the existing barrel with the new primitives.
- **Icons: `lucide-vue-next` is ALREADY a dependency** (`^0.344.0` in `package.json`)
  — no new package. Replace emoji with Lucide icons at consistent sizes/stroke like
  B2B (`<component :is="icon" :size stroke-width="2" />`).
- **Shared page contract** — one `UiPageHead` (title + description + right action) on
  every view. Adopt primitives view-by-view (Dashboard, Compression, Scanner first).

### Phase 2 — Single navigation model + committed nav (mechanisms 2 & 3)
- **New `resources/admin/config/navigation.js`** — single source of truth
  `{ name, path, icon, description, pro?, adminOnly?, group }` + helpers (`navItems`,
  `settingsItems`, `groupForPath`, `quickActions`).
- **`components/Navbar.vue`** rebuilt from `navItems`: **Dashboard · Image SEO ·
  Performance** always; **WooCommerce** (admin + `woo_active`); **Settings · Help**
  (admin). Remove dead `v-else` "Loading…" (`:36-38`). Batch stays programmatic.
- **New `components/SectionBar.vue`** — contextual sticky sub-bar per pillar via
  `groupForPath()`.
- **"Compress" → "Performance"** hub: add canonical `/performance`, redirect
  `/compression` → it (alias pattern already in `router.js:70-71`).

### Phase 3 — Settings regroup under the two pillars (mechanisms 2 & 3)
- Drive `views/Settings.vue` sidebar from `navigation.js`, grouped:
  - **General** — General, API, Permissions, License
  - **Image SEO** — Alt Text, Image Title, Generate All, File Renaming, Integrations
    (WooCommerce + SEO as visible sub-items, not buried deeper)
  - **Performance** — Compression, **Next-gen formats** (new), Image Fallbacks
- One-line description under each item. All 13 child routes keep working;
  `provide('settingsContext')` untouched.

### Phase 4 — Dashboard calm + discovery consolidation (mechanisms 4 & 5)
- **`views/Dashboard.vue`:** two **pillar health cards** (Image SEO / Performance) as
  hero (one score + one action each), tidy secondary stat row, needs-attention queue
  that renders only when non-empty. Quick actions pulled from `navigation.js`.
- **Discovery 10 → small consistent set:** Fallbacks 5 → 1–2; API-key → one prompt;
  Get Pro → one quiet Navbar placement. Retire the standalone promo card
  (`Dashboard.vue:75-110`) and wp-admin "What's New" notice (`AdminNotice.php:31-73`)
  or fold into the unified pattern (keep `AdminNotice::dismiss` route + meta intact).
- Uniform `UiEmptyState` / `UiSkeleton` / toast everywhere.

### Phase 5 — Next-gen formats: WebP/AVIF (new feature, Performance pillar)
Sidecar + `<picture>`, non-destructive. **No DB migration** — reuse the existing
`compressions.method` column (`VARCHAR(20)`) with values `'webp'`/`'avif'`, and store
sidecar file paths in post meta (mirrors the compression backup manifest).

- **Engine** — extend `Services/ImageCompressor.php` (the `reencodeFull()` choke point,
  `:290-374`, and `SUPPORTED_MIMES` `:34`) with a **new sidecar generator**: for an
  attachment + all its sizes, write `name.webp` (free) and `name.avif` (Pro) *beside*
  the originals; never touch the source file, `_wp_attached_file`, or its URL. Store a
  `_icais_nextgen` post-meta manifest (paths + sizes) for delivery + cleanup.
- **Capability detection** (new): `Imagick::queryFormats('WEBP'|'AVIF')`,
  `function_exists('imagewebp'|'imageavif')`, `wp_image_editor_supports(['mime_type' =>
  'image/avif'])`. All guarded (`class_exists`/`function_exists`) per fatal-safety.
  **AVIF caveat:** GD `imageavif()` requires PHP 8.1+ and Imagick AVIF needs the right
  delegate — with the plugin's PHP 7.4 floor, AVIF will be unavailable on many hosts.
  AVIF is therefore **capability-gated Pro**: the UI must show a distinct "server
  doesn't support AVIF" state (not just a Pro lock), and `canAvif` must reflect the
  real server capability, so Pro users on old hosts see why.
- **Cleanup on delete** (required — prevents orphaned disk bloat): guarded
  `delete_attachment` hook removes all sidecar files listed in the `_icais_nextgen`
  manifest when an attachment is deleted. Defensive (`try/catch (\Throwable)`), never
  throws into the delete flow.
- **Delivery** — **new `includes/NextGen.php`** (second public-frontend surface, modeled
  on `Fallback.php`): opt-in, default off, `is_admin()` bail. Primary hook:
  **`wp_content_img_tag`** (WP 6.0+, per-`<img>` filter — far safer than regexing
  `the_content` and avoids page-builder breakage), wrapped in a WP-version/`has_filter`
  guard since the plugin floor is WP 5.8; plus `post_thumbnail_html` /
  `wp_get_attachment_image` for non-content surfaces. Wrap `<img>` in `<picture>` with
  `<source>` for avif/webp + original fallback, only when a sidecar exists; skip
  images already inside a `<picture>`. Register guarded in `App.php` load order.
- **REST + bulk** (in the `compressor` group so `RoleGate` applies): `POST
  /compressor/convert`, `GET /compressor/convert-scan`, `POST /compressor/convert-clean`
  (remove sidecars). **Bulk conversion rides the existing `BatchController` as
  `type='convert'`** (same pattern as `'compress'`/`'meta'` — progress UI, resume,
  retry for free), not a new ad-hoc loop. Conversions count into the **existing 20/day
  compress counter** (`UsageLimiter::canCompress`/`recordCompressUsage`) — no third
  limit invented; `ICAIS_PRO` bypasses as usual. AVIF/auto gated by `ICAIS_PRO` (Pro
  add-on registers auto-on-upload `add_attachment`, like auto-compress — free
  registers none).
- **Settings** (additive, opt-in, default off): reuse dormant
  `icais_compression_convert_webp`; add `icais_compression_convert_avif` (Pro),
  `icais_nextgen_delivery_enabled` (frontend rewrite master). Register in
  `Settings::$defaults` + `SettingsController` sanitization. New Pro filter
  `icais_compression_formats` (free returns `['webp']`; Pro adds `'avif'`).
- **Frontend:** Performance view gets a **Next-gen formats** section (scan → convert →
  bulk → clean, Pro-locked AVIF/auto rows using the existing opacity-60 + Pro-badge +
  `:disabled="!isPro"` pattern); new `components/Settings/NextGenSettings.vue`. Expose
  `canWebp`/`canAvif` via `window.icais` in `Enqueue.php` (guarded).
- **Free vs Pro:** Free = manual WebP (single + bulk) + `<picture>` delivery; Pro =
  AVIF + auto-convert-on-upload.

---

## Backwards-compat & fatal-safety guardrails
- No `wp_options` key/REST field/route removed; new settings additive/opt-in; superseded
  paths get redirect aliases.
- Role gating unchanged (`router.js` guard, `RoleGate`, `Menu.php`, `canUse`).
- **`NextGen.php` and the sidecar generator** touch external encoders → wrap Imagick/GD
  calls in `class_exists`/`function_exists` + `try/catch (\Throwable)`; the frontend
  filter does cheap key/flag bails first (like `Fallback.php`). **Run `/fatal-safety` on
  all PHP** (this phase especially — new public surface + external encoders).
- No new npm dependency — `lucide-vue-next` already present; import only used icons
  (they tree-shake).

## Critical files
- Design system: new `resources/admin/design/tokens.css` + `@theme` block in existing
  `resources/scss/index.css` (Tailwind v4 — no tailwind.config.js exists); new
  `resources/admin/@ui/Ui*.vue` added to the existing `@ui/index.js` barrel
- Nav (new): `config/navigation.js`, `components/SectionBar.vue`; edit `Navbar.vue`,
  `router.js`
- Settings: `views/Settings.vue`, `components/Settings/*` (+ new `NextGenSettings.vue`)
- Dashboard: `views/Dashboard.vue`
- Discovery: `includes/AdminNotice.php`, `includes/Enqueue.php`, `views/SetupWizard.vue`
- Next-gen: `includes/Services/ImageCompressor.php`, **new `includes/NextGen.php`**,
  `includes/Http/Controllers/CompressorController.php`, `includes/Http/api.php`,
  `includes/App.php`, `includes/Services/Settings.php`
- Docs: keep `CLAUDE.md` + `PROJECT_STATE.md` current; plan at `docs/UX_REDESIGN_PLAN.md`

## Reference — B2B plugin (STRUCTURE ONLY, not visual design)
`resources/admin/styles/tokens.css` · `config/navigation.js` · `components/TopNav.vue` +
`SectionBar.vue` · `layouts/SettingsLayout.vue` · `views/Dashboard.vue` · `@ui/*`
Study these for *how they organize* (token architecture, single nav source, layout
contract, state handling). Do NOT transplant their colors, spacing personality, button
shapes, or screen compositions — ImageCraft's UI must look like ImageCraft.

## Verification (single final gate before the one release)
1. `npm run lint` clean; `npm run prod` builds (ask before running build).
2. wp-admin sweep as **admin / permitted non-admin / Pro-flagged** — nav, sub-bar,
   settings, dashboard, discovery, Next-gen UI all correct; no 404s.
3. Next-gen end-to-end: convert a JPEG → `.webp`/`.avif` sidecars created, original
   untouched, frontend serves `<picture>` with correct fallback, "clean" removes
   sidecars, capability flags correct when Imagick/GD lack a format, **deleting the
   attachment removes its sidecars**, bulk convert runs through the Batch progress UI,
   and free-tier conversions decrement the shared 20/day compress counter.
4. Legacy paths (`/compression`, `/settings/woocommerce`, `/settings/seo`) still
   resolve via redirect; no settings/endpoint shape change.
5. Run `/fatal-safety` and `/pre-release`.
6. Update `CLAUDE.md` (Frontend Structure, Dependency Map, REST table, new
   `NextGen.php`) + prepend a `PROJECT_STATE.md` Recent Changes entry.
```
