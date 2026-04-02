# ImageCraft — Free & Pro Feature Plan

## Current Free Features (v1.x)

Everything below is free with a **50 images/day** generation limit.

### AI Generation (BYOK — Bring Your Own Key)
- Alt text generation with AI (Anthropic Claude, OpenAI GPT, Google Gemini)
- Image title generation with AI
- Image link title generation with AI (Kadence)
- SEO-friendly file renaming with AI
- Multiple tones: Professional, Casual, Technical, SEO-Optimized, Custom Prompt
- 50+ language support
- Configurable max length for alt text, title, and filename

### Block Editor Integration
- Core/Image block — "Generate with AI" button for alt text
- Kadence/Image block — alt text, title attribute, and link title buttons
- Preview before accept with accept/reject UI (Gutenberg)

### Media Library
- "Generate with AI" button in media modal attachment details
- "Generate Title" button in media modal
- "Rename File" button in media modal

### Batch Processing
- Batch alt text generation for all/selected images
- Batch title generation
- Failed image regeneration after batch processing

### WooCommerce
- Product-aware alt text generation (product name, categories, tags, SKU, price)
- Featured image generation
- Gallery image batch generation
- WooCommerce-specific tones (SEO, Descriptive, Lifestyle)

### SEO Plugin Integration
- Yoast SEO focus keyphrase injection into prompts
- Rank Math focus keyword injection
- All in One SEO keyphrase injection
- SEOPress keyword injection

### Auto-Generation
- Auto-generate alt text on image upload
- Auto-generate title on image upload
- Auto-rename file on upload
- Skip images with existing alt text/title options

### Settings & Configuration
- Per-provider model selection
- Custom prompt templates for alt text and title
- WooCommerce product info toggle
- Preview before accept (alt text and title separately)

### Dashboard & Tracking
- Generation history with status tracking (pending/accepted/rejected)
- Daily usage statistics
- Cost tracking per generation

---

## Future Pro Features

Introduce Pro after reaching **5,000+ active installs** with strong reviews and brand trust established.

### Pro Pricing Model
- One-time annual license ($49–79/year)
- Remove the 50/day generation limit (unlimited)
- All future pro features included

### Pro Feature: Unlimited Daily Generations
- Remove the 50 images/day cap
- Unlimited batch processing
- Unlimited WooCommerce processing

### Pro Feature: AI Image Metadata (IPTC/EXIF)
- AI generates keywords, descriptions, copyright, author metadata
- Writes IPTC data directly into JPEG files using PHP built-in `iptcembed()`
- Zero external dependencies — pure PHP, no libraries, no server tools
- Fields: IPTC keywords, caption, author, copyright, headline
- JPEG only (covers majority of uploaded photos)
- New prompt mode: `setMetaTagMode()` in BaseAIProvider

### Pro Feature: Scheduled/Cron Processing
- Auto-process new uploads on a schedule (hourly, nightly)
- Background processing via WP-Cron
- Email notification when batch completes

### Pro Feature: CSV Export/Import
- Export all image alt text, titles, filenames to CSV
- Edit in spreadsheet and re-import
- Useful for bulk review and manual corrections

### Pro Feature: Analytics Dashboard
- Monthly/weekly generation trends with charts
- Per-provider usage breakdown
- Cost tracking over time
- Alt text coverage percentage across the site

### Pro Feature: Additional Page Builder Integrations
- Elementor image widget — alt text + title buttons
- Beaver Builder photo module
- Divi image module

### Pro Feature: FileBird Integration
- Folder-based batch generation — select a FileBird folder, process all images
- Folder-level progress tracking

### Pro Feature: Multi-Site Support
- Network-wide settings management
- Per-site API key configuration
- Network admin dashboard with aggregated stats

### Pro Feature: Alt Text Quality Scoring
- AI evaluates existing alt text quality (0-100 score)
- Identifies placeholder, filename-based, or low-quality alt text
- Suggests improvements

### Pro Feature: Caption Generation
- Generate `<figcaption>` text with AI
- WordPress caption field support
- Separate tone and length settings

### Pro Feature: Priority Support
- Email support with faster response time
- Priority bug fixes

---

## Strategy

| Phase | Goal | Approach |
|-------|------|----------|
| **Now (v1.x)** | Build user base & brand trust | Everything free, 50/day limit, BYOK model |
| **5,000+ installs** | Introduce Pro | Unlimited generations + new advanced features |
| **10,000+ installs** | Expand Pro | More integrations, metadata, analytics |

### Key Principles
1. **BYOK is the trust signal** — users keep their own API keys, no hidden costs
2. **Free tier must be excellent** — generous enough for small sites, drives 5-star reviews
3. **Pro gates convenience, not core value** — unlimited usage + advanced workflow features
4. **No breaking changes** — existing free users must never lose features they already have
5. **Daily limit > monthly limit** — users never hit a wall and wait weeks, less frustration
