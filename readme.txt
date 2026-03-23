=== ImageCraft – AI Alt Text, File Renamer & Image SEO ===
Contributors: softminal, diptosoftminal
Tags: alt text, ai alt text, image seo, accessibility, woocommerce
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate AI-powered alt text for image SEO and accessibility using your own Anthropic, OpenAI, or Gemini API key. Bring Your Own Key — no middleman, no per-image credits.

== Description ==

ImageCraft is a **Bring Your Own Key (BYOK)** plugin that generates alt text for WordPress images using Anthropic Claude, OpenAI GPT-4, or Google Gemini. You provide your own API key and pay only for direct API usage — **no middleman service, no per-image credits.**

= Why ImageCraft? =

Most alt text plugins charge monthly subscriptions or per-image credits through their own proxy service. ImageCraft connects directly to your AI provider — you pay only the provider's standard API rate with zero markup.

= Key Features =

* **3 AI providers, 15+ models** — Choose from Anthropic Claude (Haiku, Sonnet, Opus), OpenAI (GPT-4o, GPT-4.1, GPT-5), and Google Gemini (Flash, Pro). Switch providers and models anytime.
* **Gutenberg & Media Modal integration** — Generate alt text with one click directly from the block editor sidebar or the WordPress media modal. Preview, accept, or reject without leaving the editor.
* **SEO image file renaming** — Automatically rename image files to SEO-friendly slugs based on generated alt text. Works on upload or in bulk.
* **SEO keyphrase integration** — Pulls the focus keyphrase from Yoast SEO, Rank Math, All in One SEO, or SEOPress and naturally weaves it into generated alt text.
* **Image SEO audit dashboard** — Scores your media library health. Detects missing, generic/placeholder, and too-long alt text with a visual breakdown.
* **WooCommerce product awareness** — Sends product name, SKU, categories, and price as context. Includes 3 dedicated product tones (SEO, Descriptive, Lifestyle) plus custom prompt support.
* **Batch processing** — Process up to 500 images at once with real-time progress tracking. Review and edit all generated alt text in a batch review modal before saving.
* **65+ languages** — Generate alt text in your site's language. Supports English, Spanish, French, German, Japanese, Chinese, Arabic, Hindi, Tamil, Korean, and 55+ more.
* **3-minute setup wizard** — Guided wizard with provider cost comparison gets you running fast.

= All Features =

* 3 AI providers with 15+ model options
* Generate from Gutenberg editor and WordPress media modal
* Preview / accept / reject workflow
* SEO-friendly file renaming (single and bulk)
* Auto-rename on upload
* SEO audit score with quality breakdown
* Generic and placeholder alt text detection
* Too-long alt text detection
* Focus keyphrase integration (Yoast, Rank Math, AIOSEO, SEOPress)
* Dedicated WooCommerce dashboard with category filtering
* WooCommerce gallery image bulk generation
* Batch processing up to 500 images with progress bar
* Batch review & edit modal with inline editing
* Cross-page bulk selection
* Advanced filters (date range, file size, alt text quality)
* Auto-generate on new image uploads
* Skip images with existing alt text
* 7 tone options: Professional, Casual, Technical, SEO, and 3 WooCommerce tones
* Custom prompt builder with {max_length} placeholder
* Max character length control
* 65+ language support
* Usage & cost tracking dashboard with charts
* Generation history with status tracking
* Post/page title context awareness for smarter alt text
* WooCommerce auto-generate on product image upload
* Configurable batch size and request timeout
* Connection test before saving API key
* Recent activity feed on dashboard
* Guided setup wizard with provider cost comparison
* Modern SPA interface — no page reloads
* Improves WCAG accessibility compliance for screen readers

= Who Is This For? =

* **Bloggers & content creators** — Fix hundreds of missing alt texts in minutes, not hours
* **SEO professionals** — Keyphrase-aware alt text that aligns with your on-page SEO strategy
* **WooCommerce store owners** — Product-aware alt text with name, category, and SKU context for every product image
* **Agencies** — Batch process entire media libraries for client sites. BYOK means no per-site licensing
* **Accessibility-conscious site owners** — Improve WCAG compliance and screen reader experience across your entire site

= How It Works =

1. **Connect** — Add your API key from Anthropic, OpenAI, or Google Gemini (3-minute setup wizard)
2. **Generate** — Click "Generate with AI" on any image, or batch process up to 500 images at once
3. **Review** — Preview generated alt text, edit if needed, then accept. Or enable auto-accept for hands-free processing

= Typical API Costs =

You pay only for API usage directly to your chosen provider. Since ImageCraft uses a BYOK model, there is no markup — you get the same rate as any developer using the API directly. Check your provider's pricing page for current rates:

* [Anthropic Claude pricing](https://www.anthropic.com/pricing)
* [OpenAI pricing](https://openai.com/api/pricing/)
* [Google Gemini pricing](https://ai.google.dev/pricing)

= ImageCraft vs Other Plugins =

* **vs subscription plugins** — No monthly fees. Pay per use at direct API rates with zero markup.
* **vs single-provider plugins** — 3 providers, 15+ models. Switch anytime without losing data or reconfiguring.
* **vs alt-text-only plugins** — ImageCraft also renames files for SEO, runs an SEO audit, and integrates with your SEO plugin's focus keyphrase.
* **vs manual alt text** — Batch process 500 images in minutes instead of writing each one by hand. AI understands image content, product context, and SEO keywords.

= Privacy & Security =

* API keys encrypted with AES-256-CBC using WordPress salts
* Images sent only to your chosen AI provider when you trigger generation
* SSRF protection on image URL fetching
* No data routed through third-party proxy services
* All plugin logic runs on your server

== External services ==

This plugin connects to third-party AI services to generate alt text for your images. No data is sent automatically — requests are only made when you explicitly trigger alt text generation (manually, via batch processing, or if you enable auto-generate on upload).

**Data sent:** When alt text generation is triggered, the plugin sends the image (base64-encoded) along with a text prompt to your chosen AI provider. No personal user data is sent. Your API key is included in the request header for authentication.

= Anthropic (Claude) =

Used to generate alt text via the Claude vision API.

* Endpoint: `https://api.anthropic.com/v1/messages`
* [Terms of Service](https://www.anthropic.com/legal/consumer-terms)
* [Privacy Policy](https://www.anthropic.com/legal/privacy)

= OpenAI (GPT-4) =

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
3. Follow the setup wizard to add your API key
4. Start generating alt text!

== Frequently Asked Questions ==

= How does pricing work? =

ImageCraft uses a Bring Your Own Key model — you pay only for API usage directly to your chosen provider at their standard rate. No per-image credits, no middleman markup.

= Which AI provider should I choose? =

**Anthropic Claude Haiku** offers the best balance of quality and cost for most sites. **Google Gemini Flash** is the cheapest option. **OpenAI GPT-4o** produces excellent results at a higher cost. You can test each provider with the built-in connection test and switch anytime.

= How many AI models can I choose from? =

ImageCraft supports 15+ models across 3 providers. You can select the specific model per provider (e.g., Claude Haiku for cost-efficiency or Claude Opus for maximum quality).

= Is my API key safe? =

Yes. Your API key is encrypted with AES-256-CBC using your WordPress security salts before being stored in the database. Keys are never sent anywhere except to your chosen AI provider.

= Does it work with WooCommerce? =

Yes! ImageCraft has a dedicated WooCommerce dashboard. It sends product name, SKU, categories, and price as context to the AI. Includes 3 WooCommerce-specific tones (Product SEO, Product Descriptive, Product Lifestyle) and supports gallery image bulk generation.

= Can I generate alt text from the editor? =

Yes. ImageCraft adds a "Generate with AI" button directly in the Gutenberg block editor sidebar and the WordPress media modal. You can preview, accept, or reject the generated text without leaving the editor.

= Does it rename image files for SEO? =

Yes. ImageCraft renames image files to SEO-friendly slugs based on the alt text. Works on individual images, in bulk, or automatically on upload.

= Can I generate alt text in my language? =

Yes. The plugin supports 65+ languages including Spanish, French, German, Japanese, Chinese, Arabic, Hindi, Tamil, Persian, Korean, and many more.

= I have thousands of images with no alt text. Can ImageCraft handle that? =

Yes. Use the media library scanner to filter images missing alt text, select them all with the cross-page bulk select, and start a batch job. ImageCraft processes up to 500 images per batch with real-time progress tracking. You can review and edit every generated alt text before saving.

= Can I write my own prompt? =

Yes. ImageCraft includes a custom prompt builder for both general images and WooCommerce products. Use the {max_length} placeholder to auto-insert your character limit setting.

= Does it overwrite my existing alt text? =

Only if you choose to. The "Skip images with existing alt text" option is available in settings. When preview mode is enabled, nothing is saved until you explicitly accept it.

= What image formats are supported? =

JPG, PNG, GIF, and WebP. Images are read from your local WordPress uploads directory when possible (avoiding SSL issues on localhost) or fetched via URL for remote images.

= Does it work with page builders like Elementor or Divi? =

Yes. Alt text is stored on the standard WordPress `_wp_attachment_image_alt` meta field, which all page builders read. Any alt text generated by ImageCraft will appear in Elementor, Divi, Beaver Builder, and other builders automatically.

== Screenshots ==

1. Dashboard - Image SEO audit score, stats cards, usage & cost chart, and generation results overview
2. Media Library - Generate alt text and rename files directly from the WordPress media modal with one click
3. WooCommerce Products - Browse products with alt text status, filter by category, and generate alt text per product
4. Settings - API configuration with provider and model selection, API key management, and connection testing
5. Gutenberg Editor - Generate alt text with AI directly from the block editor image settings panel

== Changelog ==

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
* Batch processing up to 500 images with progress tracking and review modal
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

= 1.1.0 =
65+ language support, Help page with video tutorial, improved setup wizard, and bug fixes.

= 1.0.0 =
Initial release.
