=== ImageCraft – AI Alt Text, File Renamer & Image SEO ===
Contributors: softminal, diptosoftminal
Tags: alt text, ai, image seo, accessibility, file renamer
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate SEO-aware and WooCommerce-context-aware alt text using your own API key from Anthropic, OpenAI, or Google Gemini.

== Description ==

ImageCraft is a Bring Your Own Key (BYOK) plugin that generates alt text for WordPress images using Anthropic Claude, OpenAI GPT-4, or Google Gemini. You provide your own API key and pay only for direct API usage — no middleman service, no subscriptions, no per-image credits.

**What makes this plugin different?**

Most alt text plugins in the directory either route requests through their own paid service or support only a single AI provider. ImageCraft takes a different approach by combining multi-provider BYOK with SEO and WooCommerce awareness:

* **3 AI providers, your choice** — Switch between Anthropic Claude, OpenAI GPT-4, and Google Gemini using your own API keys. Compare quality and cost, and pick what works for your site.
* **SEO keyphrase-aware generation** — Integrates with Yoast SEO, Rank Math, All in One SEO, and SEOPress to pull the focus keyphrase from your posts and include it in the generated alt text. This means your alt text aligns with your existing SEO strategy without manual effort.
* **WooCommerce product-context generation** — Sends product name, SKU, categories, tags, and price as context to the AI, so generated alt text is relevant to the product — not just a generic image description. Includes 3 dedicated WooCommerce tone modes (Product SEO, Product Descriptive, Product Lifestyle) and a custom prompt option.
* **SEO image audit** — Scans your media library and scores alt text quality. Detects missing alt text, generic/placeholder text (e.g. "image", "IMG_1234"), filename-based alt text, and text that exceeds recommended length.
* **Encrypted API key storage** — API keys are encrypted with AES-256-CBC using your WordPress security salts before being stored in the database.

**Additional Features**

* Scan and filter your media library (by date range, file size, alt text quality)
* Batch processing up to 500 images with progress tracking
* Auto-generate alt text on new image uploads
* 7 tone options: Professional, Casual, Technical, SEO, and 3 WooCommerce-specific tones
* Custom prompt builder — write your own AI instructions
* Review and edit generated alt text before accepting
* SEO-friendly file renaming based on alt text
* WooCommerce gallery image support
* Multi-language support (30+ languages)
* Generate alt text directly from the Gutenberg editor and WordPress media modal
* Usage statistics and cost tracking dashboard

**Typical API Costs**

You pay only for API usage to your chosen provider:
* Anthropic Claude: ~$0.0008 per image
* OpenAI GPT-4: ~$0.01 per image
* Google Gemini: ~$0.001 per image

**Privacy & Security**

* API keys encrypted with AES-256-CBC using WordPress salts
* Images are sent only to your chosen AI provider when you trigger generation
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


== Source Code ==

The uncompiled source code for all JavaScript and CSS assets is included in the `resources/` directory of this plugin. It is also available on GitHub:

[https://github.com/softminal/imagecraft-ai-alt-text-file-renamer-image-seo](https://github.com/softminal/imagecraft-ai-alt-text-file-renamer-image-seo)

To build from source:

1. Install [Node.js](https://nodejs.org/) (v18+)
2. Run `npm install` to install dependencies
3. Run `npm run dev` for development (Vite dev server on port 4000)
4. Run `npm run prod` to build production assets into `assets/`

== How to Use ImageCraft ==

https://www.youtube.com/watch?v=VVhnzSmrjLE

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/imagecraft-ai-alt-text-file-renamer-image-seo/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Follow the setup wizard to add your API key
4. Start generating alt text!

== Frequently Asked Questions ==

= How does pricing work? =

The plugin itself has no premium tiers or subscriptions. You pay only for the API usage to your chosen provider (Anthropic, OpenAI, or Google Gemini). See the "Typical API Costs" section above for estimates.

= Which AI provider should I choose? =

Anthropic Claude offers a good balance of quality and cost. OpenAI and Google Gemini are also well-supported options. You can test each provider using the built-in connection test.

= Is my API key safe? =

Yes. Your API key is encrypted and stored securely in your WordPress database.

= Does it work with WooCommerce? =

Yes! ImageCraft includes WooCommerce-specific prompts optimized for product images, gallery images, and lifestyle photos.

= Can I generate alt text in my language? =

Yes. The plugin supports 30+ languages including Spanish, French, German, Japanese, Chinese, Arabic, and more.

== Screenshots ==

1. Dashboard - Image SEO audit score, stats cards, usage & cost chart, and generation results overview
2. Media Library - Generate alt text and rename files directly from the WordPress media modal with one click
3. WooCommerce Products - Browse products with alt text status, filter by category, and generate alt text per product
4. Settings - API configuration with provider and model selection, API key management, and connection testing
5. Gutenberg Editor - Generate alt text with AI directly from the block editor image settings panel

== Changelog ==

= 1.0.0 =
* Initial release
* Anthropic Claude, OpenAI GPT-4, and Google Gemini support
* Media library scanner with advanced filters
* Batch processing with progress tracking
* Auto-generate on upload
* Multiple tone options (Professional, Casual, Technical, SEO)
* Custom prompt builder
* Multi-language support (30+ languages)
* Image SEO audit report with quality score
* WooCommerce product image support with optimized prompts
* SEO-friendly file renaming
* Bulk select with date range, quality, and file size filters
* Usage statistics dashboard
* API key encryption

== Upgrade Notice ==

= 1.0.0 =
Initial release.
