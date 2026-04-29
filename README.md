# ImageCraft Alt Text, File Renaming and Image SEO

A WordPress plugin that generates AI-powered alt text for images using your own API key from Anthropic Claude, OpenAI GPT-4, or Google Gemini.

## Features

- **3 AI providers** — Anthropic Claude, OpenAI GPT-4, and Google Gemini (BYOK)
- **SEO keyphrase-aware** — Integrates with Yoast SEO, Rank Math, All in One SEO, and SEOPress
- **WooCommerce-aware** — Uses product context (name, SKU, categories, price) for relevant alt text
- **SEO image audit** — Scans media library and scores alt text quality
- **Batch processing** — Process up to 500 images with progress tracking
- **Auto-generate** — Generate alt text on new image uploads
- **7 tone options** — Professional, Casual, Technical, SEO, and 3 WooCommerce-specific tones
- **Custom prompts** — Write your own AI instructions
- **File renaming** — SEO-friendly file renaming based on alt text
- **Multi-language** — Supports 30+ languages
- **Encrypted storage** — API keys encrypted with AES-256-CBC using WordPress salts

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+

## Installation

1. Upload the plugin files to `/wp-content/plugins/imagecraft/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Follow the setup wizard to add your API key

## Development

```bash
# Install dependencies
npm install
composer install

# Development server (Vite on port 4000 with HMR)
npm run dev

# Production build
npm run prod

# Create distribution ZIP
./build.sh
```

## Tech Stack

- **Backend:** PHP 7.4+ with PSR-4 autoloading
- **Frontend:** Vue 3 + Tailwind CSS + Vite
- **REST API:** `/wp-json/imagecraft/v1`

## License

GPL-2.0+
