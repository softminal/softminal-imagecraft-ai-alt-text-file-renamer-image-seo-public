=== ImageCraft Alt Text, File Renaming and Image SEO ===
Contributors: softminal, diptosoftminal
Tags: ai alt text, image alt text generator, image seo, alt text generator, accessibility
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.5
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered alt text, file renaming, and image SEO for WordPress. Use OpenAI, Claude, or Gemini with your own API key (BYOK).

== Description ==

ImageCraft helps you generate SEO-optimized alt text, rename image files, and improve image accessibility in WordPress using OpenAI, Claude, or Gemini with your own API key.

Generate SEO-friendly alt text for hundreds of images in minutes instead of hours.

= Why ImageCraft? =

Most alt text plugins charge monthly subscriptions or per-image credits through their own proxy service. ImageCraft connects directly to your AI provider — you pay only the provider's standard API rate with zero markup.

= Core Features =

* AI Image Alt Text Generator (SEO optimized)
* AI-powered File Renamer for better image SEO
* Image SEO Audit Dashboard with quality scoring
* SEO keyphrase integration — works with **Yoast SEO**, **Rank Math**, **AIOSEO**, and **SEOPress**
* **WooCommerce** product-aware alt text generation
* **Batch processing** images with real-time progress
* Use your own API key — no subscription, no markup

= Key Features =

* **3 AI providers, 17+ models** — Choose from Anthropic Claude, OpenAI, and Google Gemini models. Switch providers and models anytime.
* **Gutenberg, Kadence, Spectra & Media Modal integration** — Generate alt text with one click from the block editor sidebar, **Kadence** image block, **Spectra** image block, or WordPress media modal. Preview, accept, or reject without leaving the editor.
* **Spectra Block support** — Full integration with **Spectra** image block: generate alt text and title directly from the block inspector sidebar.
* **Kadence Blocks support** — Full integration with **Kadence** image block: generate alt text, title attribute, and link title directly from the block inspector sidebar.
* **AI-powered file renaming** — AI generates SEO-friendly filenames directly from image content. Independent from alt text and title — each is a separate AI call.
* **SEO keyphrase integration** — Pulls the focus keyphrase from **Yoast SEO**, **Rank Math**, **All in One SEO**, or **SEOPress** and naturally weaves it into generated alt text.
* **FileBird integration** — Filter the scanner by **FileBird** folders and use folder context to generate more specific alt text prompts.
* **Image SEO audit dashboard** — Scores your media library health. Detects missing, generic/placeholder, and too-long alt text with a visual breakdown.
* **WooCommerce product awareness** — Sends product name, SKU, categories, and price as context. Includes 3 dedicated product tones (SEO, Descriptive, Lifestyle) plus custom prompt support.
* **Batch processing**
* **65+ languages** — Generate alt text in your site's language. Supports English, Spanish, French, German, Japanese, Chinese, Arabic, Hindi, Tamil, Korean, and 55+ more.

= All Features =

* 3 AI providers with 17+ model options
* Generate from **Gutenberg** editor, **Kadence** blocks, **Spectra** blocks, and WordPress media modal
* **Kadence** image block integration — alt text, title attribute, and link title generation
* **Spectra** image block integration — alt text and title generation
* Preview / accept / reject workflow (separate settings for alt text and title)
* AI-powered link title generation for **Kadence** image blocks
* AI-powered file renaming — AI generates filenames from image content
* **FileBird** folder filtering in the media scanner
* **FileBird** folder context added to AI prompts for more relevant alt text
* SEO audit score with quality breakdown
* Generic and placeholder alt text detection
* Too-long alt text detection
* Focus keyphrase integration (**Yoast**, **Rank Math**, **AIOSEO**, **SEOPress**)
* Dedicated **WooCommerce** dashboard with category filtering
* **WooCommerce** gallery image bulk generation
* Resume interrupted batch jobs — pick up where you left off after browser close
* Single-owner concurrency control — prevents duplicate processing across tabs
* Separate batch buttons for alt text and title generation
* Advanced filters (date range, file size, alt text quality)
* Auto-generate on new image uploads
* Post type exclusion — skip auto-generation for Pages, custom post types, etc.
* Skip images with existing alt text (auto-upload and batch processing)
* 7 tone options: Professional, Casual, Technical, SEO, and 3 WooCommerce tones
* Custom prompt builder with {max_length} placeholder
* Max character length control
* 65+ language support
* Usage & cost tracking dashboard with charts
* Generation history with status tracking
* Post/page title context awareness for smarter alt text
* **WooCommerce** auto-generate on product image upload
* Configurable batch size and request timeout
* Recent activity feed on dashboard
* Guided setup wizard with provider cost comparison
* Modern SPA interface — no page reloads
* Improves WCAG accessibility compliance for screen readers

= Boost Google Image Rankings =

ImageCraft not only generates alt text — it also renames your image files using AI. This improves your chances of ranking in Google Image Search and drives more organic traffic to your site.

= Free to Start, No Lock-in =

* 50 images per day — no account or signup required
* No per-image credits or proxy services
* Use your own API key — pay only your AI provider directly
* Full control over your data

= Who Is This For? =

* **Bloggers & content creators** — Fix hundreds of missing alt texts in minutes, not hours
* **SEO professionals** — Keyphrase-aware alt text that aligns with your on-page SEO strategy
* **WooCommerce store owners** — Product-aware alt text with name, category, and SKU context for every product image
* **Agencies** — Batch process entire media libraries for client sites. BYOK means no per-site licensing
* **Accessibility-conscious site owners** — Improve WCAG compliance and screen reader experience across your entire site

= How It Works =

1. **Connect** — Add your API key in your WordPress site from Anthropic, OpenAI, or Google Gemini (3-minute setup wizard)
2. **Generate** — Click "Generate with AI" on any image, or batch process images at once
3. **Review** — Preview generated alt text, edit if needed, then accept. Or enable auto-accept for hands-free processing

= Typical API Costs =

You pay only for API usage directly to your chosen provider. Since ImageCraft uses a BYOK model, there is no markup — you get the same rate as any developer using the API directly. Check your provider's pricing page for current rates:

* [Anthropic Claude pricing](https://www.anthropic.com/pricing)
* [OpenAI pricing](https://openai.com/api/pricing/)
* [Google Gemini pricing](https://ai.google.dev/pricing)

= ImageCraft vs Other Plugins =

* **vs subscription plugins** — No monthly fees. Pay per use at direct API rates with zero markup.
* **vs single-provider plugins** — 3 providers, 17+ models. Switch anytime without losing data or reconfiguring.
* **vs alt-text-only plugins** — ImageCraft also renames files for SEO, runs an SEO audit, and integrates with your SEO plugin's focus keyphrase.

= Privacy & Security =

* API keys encrypted with AES-256-CBC using WordPress salts (or stored in wp-config.php for file-based security)
* Images sent only to your chosen AI provider when you trigger generation
* SSRF protection on image URL fetching
* No data routed through third-party proxy services

== External services ==

This plugin connects to third-party AI services to generate alt text for your images. No data is sent automatically — requests are only made when you explicitly trigger alt text generation (manually, via batch processing, or if you enable auto-generate on upload).

**Data sent:** When alt text generation is triggered, the plugin sends the image (base64-encoded) along with a text prompt to your chosen AI provider. No personal user data is sent. Your API key is included in the request header for authentication.

= Anthropic (Claude) =

Used to generate alt text via the Claude vision API.

* Endpoint: `https://api.anthropic.com/v1/messages`
* [Terms of Service](https://www.anthropic.com/legal/consumer-terms)
* [Privacy Policy](https://www.anthropic.com/legal/privacy)

= OpenAI =

Used to generate alt text via the OpenAI chat completions API.

* Endpoint: `https://api.openai.com/v1/chat/completions`
* [Terms of Use](https://openai.com/policies/terms-of-use/)
* [Privacy Policy](https://openai.com/policies/privacy-policy/)

= Google Gemini =

Used to generate alt text via the Google Gemini API.

* Endpoint: `https://generativelanguage.googleapis.com/v1beta/models/`
* [Terms of Service](https://ai.google.dev/gemini-api/terms)
* [Privacy Policy](https://policies.google.com/privacy)

== How to Use ImageCraft ==

https://www.youtube.com/watch?v=VVhnzSmrjLE

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/imagecraft-ai-alt-text-file-renamer-image-seo/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Follow the setup wizard to add your API key in your WordPress site
4. Start generating alt text!

== Frequently Asked Questions ==

= Is my API key safe? =

Yes. Your API key is encrypted with AES-256-CBC using your WordPress security salts before being stored in the database. You can also define your key in wp-config.php using `define('ICAIS_API_KEY', 'your-key');` to keep it out of the database entirely. Keys are never sent anywhere except to your chosen AI provider.

= How does pricing work? =

ImageCraft uses a Bring Your Own Key model — you pay only for API usage directly to your chosen provider at their standard rate. No per-image credits, no middleman markup.

= Which AI provider should I choose? =

**Anthropic Claude Haiku** offers the best balance of quality and cost for most sites. **Google Gemini Flash** is a strong lower-cost option. **OpenAI GPT-4o** produces excellent results. You can test each provider with the built-in connection test and switch anytime.

= How many AI models can I choose from? =

ImageCraft supports 17+ models across 3 providers. You can select the specific model per provider depending on your preferred balance of cost and quality.

= Does it work with WooCommerce? =

Yes! ImageCraft has a dedicated WooCommerce dashboard. It sends product name, SKU, categories, and price as context to the AI. Includes 3 WooCommerce-specific tones (Product SEO, Product Descriptive, Product Lifestyle) and supports gallery image bulk generation.

= Does it work with FileBird? =

Yes. If **FileBird** is installed, ImageCraft adds a folder filter to the media scanner so you can review and batch-process images inside a specific FileBird folder. It can also include the folder name as extra context in AI prompts for more specific alt text generation.

= Can I generate alt text from the editor? =

Yes. ImageCraft adds a "Generate with AI" button directly in the Gutenberg block editor sidebar and the WordPress media modal. You can preview, accept, or reject the generated text without leaving the editor.

= Does it rename image files for SEO? =

Yes. ImageCraft uses AI to generate SEO-friendly filenames directly from image content — independent from alt text. Enable file renaming in settings and use the "Rename File" button on individual images.

= Can I generate alt text in my language? =

Yes. The plugin supports 65+ languages including Spanish, French, German, Japanese, Chinese, Arabic, Hindi, Tamil, Persian, Korean, and many more.

= I have thousands of images with no alt text. Can ImageCraft handle that? =

Yes. Use the media library scanner to filter images missing alt text, select them all with the cross-page bulk select, and start a batch job.

= Can I write my own prompt? =

Yes. ImageCraft includes a custom prompt builder for both general images and WooCommerce products. Use the {max_length} placeholder to auto-insert your character limit setting.

= Does it overwrite my existing alt text? =

Only if you choose to. The "Skip images with existing alt text" option is available in settings. When preview mode is enabled, nothing is saved until you explicitly accept it.

= What image formats are supported? =

JPG, PNG, GIF, and WebP. Images are read from your local WordPress uploads directory when possible (avoiding SSL issues on localhost) or fetched via URL for remote images.

= Does it work with Spectra Blocks? =

Yes! ImageCraft integrates with the **Spectra** image block. It adds "Generate with AI" buttons for alt text and title directly in the block inspector sidebar.

= Does it work with Kadence Blocks? =

Yes! ImageCraft has full integration with the **Kadence** image block. It adds "Generate with AI" buttons for alt text, title attribute, and link title directly in the block inspector sidebar. Each field has its own AI prompt optimized for its purpose.

= Does ImageCraft improve SEO? =

Yes. Descriptive alt text helps search engines understand your images, improving your chances of ranking in **Google Image Search**. Combined with AI-powered file renaming and SEO keyphrase integration, ImageCraft covers the key image SEO factors.

= Is it free? =

Yes. Generate up to 50 images per day — no account or signup required. You provide your own API key and pay only your AI provider directly.

= Does it work with page builders like Elementor or Divi? =

Yes. Alt text is stored on the standard WordPress `_wp_attachment_image_alt` meta field, which all page builders read. Any alt text generated by ImageCraft will appear in **Elementor**, **Divi**, **Beaver Builder**, and other builders automatically.

== Screenshots ==

1. Dashboard - Image SEO audit score, daily usage tracker, stats cards, usage & cost chart, and generation results overview
2. Media Library - Generate alt text and rename files directly from the WordPress media modal with one click
3. WooCommerce Products - Browse products with alt text status, filter by category, and generate alt text per product
4. Settings - API configuration with provider and model selection, database or wp-config.php key storage, and connection testing
5. Gutenberg Editor - Generate alt text with AI directly from the block editor image settings panel
6. Spectra - Generate alt text and title for Spectra image blocks from the inspector sidebar
7. Kadence Blocks - Generate alt text, title attribute, and link title for Kadence image blocks from the inspector sidebar

== Changelog ==

= 1.3.5 (29 Apr, 2026) =
* Fix: Removed inline admin notice JavaScript in favor of WordPress script APIs
* Fix: Improved WordPress 5.8 compatibility
* Fix: Removed admin notice suppression on the plugin screen
* Fix: Restricted settings to supported AI providers only

= 1.3.4 (20 Apr, 2026) =
* New: **FileBird** integration for scanner folder filtering
* New: **FileBird** folder context added to AI prompts for more specific alt text generation
* New: **FileBird** toggle added to Integrations settings

= 1.3.3 =
* New: WooCommerce multi-image UX redesign — product cards now show image strip with featured + gallery thumbnails, coverage badge, and progress bar
* New: Product modal with editable grid view for all images (featured + gallery) with per-image and bulk generate/save support
* New: WooCommerce advanced filters — date range and price range filters for products
* New: WooCommerce filter bar redesigned to match Scanner pill pattern
* New: "Coming Soon" badge for WPML and Polylang integrations
* Improvement: WooCommerce batch operations now include all product images (featured + gallery)
* Improvement: Product API response includes image coverage stats and gallery thumbnail URLs
* Improvement: Gallery image API response includes title and has_title fields
* Improvement: Meta cache priming for better performance in WooCommerce product listing
* Improvement: WooCommerce.vue split into ProductCard and ProductModal components
* Fix: Settings boolean defaults now use string '0' instead of false for consistent type handling

= 1.3.2 =
* New: Spectra image block integration — generate alt text and title directly from the Spectra block inspector sidebar
* New: Integrations settings tab — enable or disable plugin integrations (Kadence, Spectra, Yoast SEO, Rank Math, AIOSEO, SEOPress, WooCommerce, WPML, Polylang) with category filters
* New: Image title context mode setting — choose between image-based (default) or context-based title generation using post title, product info, and SEO keyphrase
* Improvement: Scanner filters (date range, file size, per page) now persist in the URL — filters are preserved when navigating to batch processing and back
* Improvement: Integration toggles auto-save immediately without needing the Save button
* Improvement: SEO and WooCommerce settings moved under Integrations tab for better organization

= 1.3.1 =
* New: Daily generation limit (50 images/day) with automatic 24-hour reset
* Improvement: UI improvement

= 1.3.0 =
* New: Kadence Blocks integration — generate alt text, title attribute, and link title for kadence/image blocks directly from the block inspector sidebar
* New: AI-powered link title generation — dedicated prompts that describe the click destination, not the image content
* New: wp-config.php API key support — define `ICAIS_API_KEY` in wp-config.php to keep your key out of the database
* New: Daily usage dashboard — progress bar showing images generated today with 24-hour reset timer
* New: Title preview before accept — separate setting from alt text, with highlighted hint in settings UI
* New: API key storage method selector — choose between database (encrypted) or wp-config.php in settings
* New: Setup wizard detects wp-config.php API key automatically — skip manual entry if key is already defined
* Improvement: Standalone JS files (Gutenberg, Kadence, media modal) now built and minified by Vite for production
* Improvement: New EnqueueAsset helper class — centralized dev/prod asset resolution with automatic type="module" injection
* Improvement: Batch title generation now respects the configurable max title length setting
* Improvement: Batch progress bar and stats no longer flicker between image processing
* Improvement: JS files moved to resources/admin/js/ directory for better organization
* Improvement: Build script updated to exclude source JS from distribution ZIP (served from assets/ instead)

= 1.2.1 =
* New: AI-powered image title generation — independent feature with its own tone, max length, custom prompt, auto-generate on upload, and batch processing
* New: AI-powered file renaming — AI generates SEO-friendly filenames directly from image content, independent from alt text and title
* New: Better UX in Settings
* New: Generate Title button in WordPress media modal alongside Generate with AI
* New: Rename File button in media modal with confirmation dialog warning about URL changes
* New: Batch title generation — bulk generate titles for selected images using separate batch job
* Improvement: Batch processing auto-accepts results (no review modal — results applied directly)
* Improvement: File renamer now handles accented characters correctly via remove_accents()
* Improvement: Same-filename guard prevents unnecessary -1 suffix renames
* Improvement: Thumbnail suffix extraction uses regex for WordPress-standard suffixes only
* Improvement: Media modal refreshes attachment data after rename
* Improvement: Default batch size changed to 3 for smoother progress updates
* Fix: Removed double sanitization in file renamer controller
* Fix: Suppressed unserialize warnings from corrupted attachment metadata in scanner

= 1.2.0 =
* New: Resume interrupted batch processing — pick up where you left off after browser close or refresh
* New: Post type exclusion — exclude Pages, custom post types, or any public post type from auto-generation
* New: Single-owner concurrency control — prevents duplicate processing when multiple browser tabs are open
* New: Interrupted batch notification banner on Scanner page
* New: Per-page display options (12, 24, 48, 96) in the media scanner
* Improvement: Skip existing alt text now also applies to batch processing (saves API credits)
* Improvement: Batch job status validation fixes stuck jobs from older versions
* Improvement: Smoother page/filter transitions — existing images stay visible with loading overlay instead of blank "Loading..." screen
* Fix: File renamer now updates all thumbnail/size URLs in post content (fixes broken responsive images and srcset after rename)
* Fix: File renamer handles serialized post meta safely (prevents data corruption on rename)
* Fix: File renamer updates attachment guid to reflect new URL
* Fix: File renamer no longer auto-overwrites attachment title (fixes "too long title" after rename)
* Fix: Media modal generate button now always detects the correct image when switching between images without edit mode
* Fix: Media modal alt text field reference is now queried fresh on each generate click (prevents writing to stale DOM)
* Fix: Bulk rename now includes off-page images in cross-page selections
* Fix: WP object cache now properly invalidated after batch counter updates
* Fix: Select All checkbox click area limited to checkbox and label only

= 1.1.0 =
* Expanded language support from 30 to 65+ languages
* Added Help page with dedicated support, review, quick links, and video tutorial
* Added video tutorial to setup wizard welcome step
* Added server-side language validation with single source of truth architecture
* Improved readme with expanded feature descriptions and FAQs
* Removed hardcoded API prices from wizard (links to provider pricing pages instead)
* Improved provider card styling in setup wizard
* Fixed missing imports in Navbar component
* Fixed hardcoded string in ImageCard component for i18n support
* Optimized build script to exclude Vue/SCSS source from distribution ZIP

= 1.0.0 =
* Initial release
* 3 AI providers: Anthropic Claude, OpenAI GPT-4, Google Gemini with 15+ model options
* Gutenberg block editor integration with preview/accept/reject workflow
* WordPress media modal integration with generate and rename buttons
* Media library scanner with advanced filters (date range, file size, alt text quality)
* Auto-generate alt text on image upload
* 7 tone options: Professional, Casual, Technical, SEO, and 3 WooCommerce tones
* Custom prompt builder
* 65+ language support
* Image SEO audit dashboard with quality score
* SEO keyphrase integration (Yoast SEO, Rank Math, All in One SEO, SEOPress)
* SEO-friendly image file renaming (single, bulk, auto on upload)
* Dedicated WooCommerce dashboard with product context and gallery support
* Cross-page bulk selection with advanced filtering
* Usage & cost tracking dashboard with charts
* AES-256-CBC API key encryption
* 3-minute guided setup wizard

== Upgrade Notice ==

= 1.3.5 =
Improves WordPress.org compliance and release stability with cleaner script loading, better WordPress 5.8 compatibility, and tighter provider validation.

= 1.3.4 =
Adds FileBird integration for folder-based scanner filtering, more specific AI prompt context, and a new integration toggle in settings.

= 1.3.3 =
Manage woocommerce product gallery, and advanced filter UI enhancement.

= 1.3.2 =
Spectra image block integration, new Integrations settings tab, image title context mode, and batch processing improvements. Fully backwards-compatible.

= 1.3.1 =
Daily usage tracking and improved WordPress.org listing. Fully backwards-compatible.

= 1.3.0 =
Kadence Blocks integration with alt text, title, and link title generation. wp-config.php API key support for enhanced security. Daily usage tracking on dashboard. Fully backwards-compatible — all new features are opt-in.

= 1.2.1 =
New AI-powered image title generation and file renaming features. Redesigned settings page with sidebar navigation. Improved batch processing with real-time progress. Fully backwards-compatible — new features are opt-in via settings.

= 1.2.0 =
Critical file renamer fixes (broken images, serialized data corruption, stale title), media modal generation bug fix, Gmail-style cross-page selection, and per-page display options. Fully backwards-compatible — no action required after update.

= 1.1.0 =
65+ language support, Help page with video tutorial, improved setup wizard, and bug fixes.

= 1.0.0 =
Initial release.
