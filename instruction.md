# WordPress AI Alt Text Generator - Complete Development Specification

## 📋 Project Overview

**Plugin Name:** AI Alt Text Generator  
**Version:** 1.0.0  
**Model:** 100% Free Forever (BYOK - Bring Your Own Key)  
**Target Users:** WordPress site owners who need to add alt text to images  
**Core Value:** Make websites accessible using AI, completely free

---

## 🎯 Core Philosophy

- **100% Free Forever** - No paid tiers, no upsells, no hidden costs
- **User Provides API Key** - They use their own Anthropic or OpenAI account
- **Zero Friction** - Setup in 3 minutes, works immediately
- **Unlimited Usage** - Process as many images as they want
- **Best UX Possible** - Simple, clean, intuitive interface

---

## 🏗️ Technical Stack

### Required Technologies
- **WordPress:** 5.8+ (for modern React support)
- **PHP:** 7.4+ (8.0+ recommended)
- **Database:** MySQL 5.7+ or MariaDB 10.3+
- **JavaScript:** ES6+ with vue3 (admin UI)
- **APIs:** Anthropic Claude API and OpenAI GPT-4 Vision API and gemini (give option to select user)

### WordPress APIs to Use
- `add_attachment` hook (auto-generate on upload)
- `wp_get_attachment_metadata()` (get image data)
- `update_post_meta()` (save alt text)
- WP REST API (for AJAX batch processing)
- Settings API (for plugin settings)
- Transients API (for caching)


## 🗄️ Database Schema

### Table 1: API Settings
Store encrypted API keys per user.

```sql
CREATE TABLE {$wpdb->prefix}alttext_api_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    api_provider VARCHAR(20) NOT NULL, -- 'anthropic' or 'openai'
    api_key_encrypted TEXT NOT NULL,
    encryption_iv VARCHAR(32) NOT NULL, -- Initialization vector for encryption
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY user_provider_unique (user_id, api_provider),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table 2: Generation History
Track all alt text generations for undo/audit.

```sql
CREATE TABLE {$wpdb->prefix}alttext_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attachment_id BIGINT UNSIGNED NOT NULL,
    original_alt TEXT,
    generated_alt TEXT NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    api_provider VARCHAR(20) NOT NULL,
    tone VARCHAR(20) DEFAULT 'professional',
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'accepted', 'rejected'
    tokens_used INT DEFAULT 0,
    cost_estimate DECIMAL(10,6) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attachment (attachment_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table 3: Usage Statistics
Track usage per user for analytics.

```sql
CREATE TABLE {$wpdb->prefix}alttext_stats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    images_processed INT DEFAULT 0,
    images_accepted INT DEFAULT 0,
    images_rejected INT DEFAULT 0,
    total_cost_estimate DECIMAL(10,4) DEFAULT 0,
    api_provider VARCHAR(20),
    UNIQUE KEY user_date_unique (user_id, date),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table 4: Batch Jobs
Track background batch processing jobs.

```sql
CREATE TABLE {$wpdb->prefix}alttext_batch_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    total_images INT NOT NULL,
    processed_images INT DEFAULT 0,
    failed_images INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'processing', 'completed', 'paused', 'failed'
    started_at DATETIME,
    completed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🔑 Core Features - Complete List

### 1. Setup Wizard (First-Time Experience)

#### Features:
- ✅ Detect first-time user (no API key saved)
- ✅ Step-by-step guided setup
- ✅ Provider selection (Anthropic vs OpenAI)
- ✅ Embedded video tutorials
- ✅ API key testing before saving
- ✅ Clear cost estimates shown
- ✅ Success confirmation screen

#### User Flow:
1. Install plugin → Redirect to wizard
2. Welcome screen → Choose provider
3. Watch tutorial → Get API key
4. Paste key → Test connection
5. Success → Scan media library
6. Start generating

#### Technical Requirements:
- Store wizard completion in user meta: `alttext_wizard_completed`
- Redirect to wizard if not completed
- Skip wizard if API key exists
- AJAX API key validation
- Encrypted key storage immediately

---

### 2. Media Library Scanner

#### Features:
- ✅ Scan all attachment posts
- ✅ Identify images without alt text
- ✅ Show count and percentage
- ✅ Display thumbnails in list
- ✅ Filter by: all images, missing alt, has alt
- ✅ Bulk select for batch processing
- ✅ Manual refresh button

#### Technical Requirements:
```php
// Query to find images without alt text
$query = new WP_Query([
    'post_type' => 'attachment',
    'post_mime_type' => 'image',
    'post_status' => 'inherit',
    'posts_per_page' => -1,
    'meta_query' => [
        'relation' => 'OR',
        [
            'key' => '_wp_attachment_image_alt',
            'compare' => 'NOT EXISTS'
        ],
        [
            'key' => '_wp_attachment_image_alt',
            'value' => '',
            'compare' => '='
        ]
    ]
]);
```

#### Display Info:
- Total images in library
- Images missing alt text (count + %)
- Images with alt text (count + %)
- Last scan timestamp

---

### 3. AI Alt Text Generation

#### Supported Providers:

**Anthropic Claude:**
- Model: `claude-3-haiku-20240307`
- Cost: ~$0.0008 per image
- Endpoint: `https://api.anthropic.com/v1/messages`
- Max tokens: 100
- Best for: Quality + cost

**OpenAI GPT-4 Vision:**
- Model: `gpt-4-vision-preview`
- Cost: ~$0.01 per image
- Endpoint: `https://api.openai.com/v1/chat/completions`
- Max tokens: 100
- Best for: Users who already have OpenAI account

**Gemini Vision:**

#### AI Prompts by Tone:

**Professional:**
```
Analyze this image and generate a concise, professional alt text (max 125 characters) 
for accessibility. Describe the main subject, action, and relevant context. 
Do not start with "Image of" or "Photo of". 
Focus on what's important for someone who cannot see the image. 
Return only the alt text, nothing else.
```

**Casual:**
```
Look at this image and write a friendly, conversational alt text (max 125 characters) 
that describes what's happening. Keep it natural and easy to understand. 
Don't start with "Image of" or "Picture of". 
Return only the alt text, nothing else.
```

**Technical:**
```
Analyze this image and create a detailed, technical alt text (max 125 characters) 
describing specific elements, composition, and technical aspects. 
Be precise and informative. Do not use phrases like "Image of". 
Return only the alt text, nothing else.
```

**SEO-Optimized:**
```
Generate SEO-friendly alt text (max 125 characters) for this image. 
Include relevant keywords naturally while accurately describing the image. 
Make it descriptive and keyword-rich without keyword stuffing. 
Do not start with "Image of". 
Return only the alt text, nothing else.
```

#### API Request Format:

**Anthropic:**
```json
{
  "model": "claude-3-haiku-20240307",
  "max_tokens": 100,
  "messages": [
    {
      "role": "user",
      "content": [
        {
          "type": "image",
          "source": {
            "type": "url",
            "url": "https://example.com/image.jpg"
          }
        },
        {
          "type": "text",
          "text": "[PROMPT_BASED_ON_TONE]"
        }
      ]
    }
  ]
}
```

**OpenAI:**
```json
{
  "model": "gpt-4-vision-preview",
  "messages": [
    {
      "role": "user",
      "content": [
        {
          "type": "text",
          "text": "[PROMPT_BASED_ON_TONE]"
        },
        {
          "type": "image_url",
          "image_url": {
            "url": "https://example.com/image.jpg"
          }
        }
      ]
    }
  ],
  "max_tokens": 100
}
```

#### Generation Process:
1. Get image URL from attachment
2. Build API request with selected tone prompt
3. Send to API
4. Parse response
5. Extract alt text from response
6. Store in history table
7. Show preview to user
8. User accepts/rejects/edits
9. If accepted, update `_wp_attachment_image_alt` meta

---

### 4. Batch Processing

#### Features:
- ✅ Process multiple images at once
- ✅ Real-time progress bar
- ✅ Show current image being processed
- ✅ Pause/resume capability
- ✅ Cancel option
- ✅ Background processing (doesn't block UI)
- ✅ Email notification on completion (optional)
- ✅ Error handling with retry

#### Technical Implementation:

**Use WordPress REST API for AJAX:**
```php
// Register REST route
add_action('rest_api_init', function() {
    register_rest_route('alttext/v1', '/batch-process', [
        'methods' => 'POST',
        'callback' => 'alttext_batch_process_callback',
        'permission_callback' => 'alttext_check_permissions'
    ]);
});
```

**Process in chunks:**
- Process 5-10 images per request
- Use JavaScript to chain requests
- Update progress after each chunk
- Store job status in database
- Handle timeouts gracefully

**Progress Tracking:**
```javascript
// JavaScript pseudo-code
async function processBatch(imageIds) {
    const chunkSize = 5;
    const chunks = chunkArray(imageIds, chunkSize);
    
    for (let i = 0; i < chunks.length; i++) {
        const response = await fetch('/wp-json/alttext/v1/batch-process', {
            method: 'POST',
            body: JSON.stringify({ images: chunks[i] })
        });
        
        updateProgress((i + 1) * chunkSize, imageIds.length);
    }
}
```

#### Error Handling:
- Retry failed images up to 3 times
- Log errors to history table
- Show failed images in UI
- Allow manual regeneration for failed images

---

### 5. Auto-Generate on Upload

#### Features:
- ✅ Automatically generate alt text when image uploaded
- ✅ Toggle on/off in settings
- ✅ Silent background process
- ✅ Notification of completion
- ✅ Option to review before saving

#### Technical Implementation:
```php
// Hook into attachment upload
add_action('add_attachment', 'alttext_auto_generate_on_upload');

function alttext_auto_generate_on_upload($attachment_id) {
    // Check if auto-generate is enabled
    $auto_generate = get_option('alttext_auto_generate', false);
    if (!$auto_generate) return;
    
    // Check if image
    if (!wp_attachment_is_image($attachment_id)) return;
    
    // Generate alt text in background
    wp_schedule_single_event(time(), 'alttext_generate_single', [$attachment_id]);
}
```

---

### 6. Settings Page

#### Settings to Include:

**API Configuration:**
- Provider selection (Anthropic/OpenAI)
- API key management
- Test connection button
- Change API key
- View usage stats

**Generation Settings:**
- Default tone (Professional/Casual/Technical/SEO)
- Max character length (default 125)
- Auto-generate on upload (on/off)
- Preview before accepting (on/off)
- Skip images with existing alt text (on/off)

**Advanced Settings:**
- Batch size (5-20 images per batch)
- Request timeout (30-120 seconds)
- Language for alt text (English default)
- Custom prompt template (advanced users)

**Notifications:**
- Email on batch completion
- Email on errors
- In-dashboard notifications

#### Settings Storage:
```php
// Option keys
'alttext_api_provider'           // 'anthropic' or 'openai'
'alttext_default_tone'           // 'professional', 'casual', 'technical', 'seo'
'alttext_max_length'             // int, default 125
'alttext_auto_generate'          // bool
'alttext_preview_before_accept'  // bool
'alttext_skip_existing'          // bool
'alttext_batch_size'             // int, default 5
'alttext_request_timeout'        // int, default 60
'alttext_language'               // string, default 'en'
'alttext_email_on_complete'      // bool
```

---

### 7. Dashboard

#### Dashboard Sections:

**Overview Widget:**
```
┌─────────────────────────────────────────┐
│  📊 Overview                            │
├─────────────────────────────────────────┤
│  Total Images: 1,247                    │
│  Missing Alt Text: 847 (68%)            │
│  Generated This Month: 234              │
│  Success Rate: 98%                      │
│                                         │
│  API Status: ✅ Connected (Anthropic)   │
│  Estimated Cost/Image: $0.0008          │
└─────────────────────────────────────────┘
```

**Quick Actions:**
- Scan Media Library
- Generate All Alt Text
- View Settings
- Help & Documentation

**Recent Activity:**
- List of last 10 generated alt texts
- Show thumbnail, filename, generated text
- Status (pending/accepted/rejected)
- Quick actions (edit, regenerate, accept, reject)

**Usage Statistics:**
- Images processed today/week/month
- Total cost estimate
- Average processing time
- Success vs failure rate

---

### 8. Individual Image Management

#### Features in Media Library:
- Show alt text status badge on thumbnails
- "Generate Alt Text" button on each image
- Quick edit alt text inline
- View generation history for each image
- Regenerate option
- Undo to original

#### Integration Points:
- WordPress Media Library grid view
- WordPress Media Library list view
- Single image edit screen
- Bulk edit screen

---

### 9. Help & Documentation

#### In-Plugin Documentation:
- ✅ Setup guide
- ✅ FAQ section
- ✅ Video tutorials
- ✅ Troubleshooting common issues
- ✅ API provider comparison
- ✅ Cost calculator
- ✅ Keyboard shortcuts
- ✅ Contact support

#### External Resources:
- Link to plugin website
- WordPress.org support forum
- GitHub issues (if open source)
- YouTube channel
- Blog with tutorials

---

## 🔒 Security Requirements

### 1. API Key Encryption

**Use WordPress encryption:**
```php
class AltText_Encryption {
    private function get_encryption_key() {
        // Use WordPress salts for key
        return hash('sha256', AUTH_KEY . SECURE_AUTH_KEY);
    }
    
    public function encrypt($data) {
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    
    public function decrypt($data) {
        $key = $this->get_encryption_key();
        list($encrypted, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
```

### 2. Data Sanitization

**Always sanitize:**
- API keys: `sanitize_text_field()`
- User inputs: `sanitize_text_field()`, `esc_html()`
- URLs: `esc_url()`
- SQL queries: Use `$wpdb->prepare()`

### 3. Nonce Verification

**All AJAX requests must verify nonce:**
```php
wp_verify_nonce($_POST['nonce'], 'alttext_action_name');
```

### 4. Capability Checks

**Verify user permissions:**
```php
if (!current_user_can('upload_files')) {
    wp_die('Unauthorized');
}
```

### 5. Rate Limiting

**Prevent abuse:**
- Limit API calls per user per minute
- Use transients to track requests
- Block excessive requests

---

## 🎨 UI/UX Guidelines

### Design Principles:
1. **Clean & Minimal** - No clutter, focus on essentials
2. **WordPress Native** - Use WordPress admin styles
3. **Mobile Friendly** - Responsive design
4. **Accessible** - WCAG 2.1 AA compliant
5. **Fast** - Optimistic UI updates, no blocking

### Color Scheme:
- Primary: WordPress blue (#0073aa)
- Success: Green (#46b450)
- Warning: Orange (#f0b849)
- Error: Red (#dc3232)
- Neutral: WordPress grays

### Typography:
- Use WordPress default fonts
- Clear hierarchy (h1, h2, h3)
- Readable sizes (16px minimum)

### Components to Use:
- WordPress buttons (`.button`, `.button-primary`)
- WordPress form fields
- WordPress notices (`.notice`)
- WordPress cards (`.card`)
- WordPress progress bars

---

## 📊 Analytics & Tracking

### Internal Analytics (No External Services):

**Track in Database:**
- Total images processed
- Success rate
- Average processing time
- Most used tone
- Provider usage (Anthropic vs OpenAI)
- Peak usage times
- User retention (active users per month)

**Dashboard Analytics Widget:**
```
┌─────────────────────────────────────────┐
│  📈 Your Usage Stats                    │
├─────────────────────────────────────────┤
│  This Month: 234 images                 │
│  Total Processed: 1,547 images          │
│  Estimated Cost: $1.24                  │
│  Average Time: 2.3 seconds/image        │
│  Success Rate: 98%                      │
└─────────────────────────────────────────┘
```

---

## 🚀 Performance Optimization

### 1. Caching Strategy

**Cache API responses:**
```php
// Cache generated alt text for 30 days
set_transient('alttext_cache_' . $attachment_id, $generated_alt, 30 * DAY_IN_SECONDS);
```

**Cache image list:**
```php
// Cache scanned images for 1 hour
set_transient('alttext_scanned_images', $images, HOUR_IN_SECONDS);
```

### 2. Database Optimization

**Index frequently queried columns:**
- Already included in schema above
- Use EXPLAIN to optimize slow queries

**Limit query results:**
```php
// Paginate large result sets
'posts_per_page' => 50,
'paged' => $current_page
```

### 3. Asset Loading

**Load scripts only where needed:**
```php
// Only on plugin pages
if (isset($_GET['page']) && strpos($_GET['page'], 'alttext') !== false) {
    wp_enqueue_script('alttext-admin-js');
}
```

**Minify CSS/JS in production:**
- Use minified versions for production
- Keep non-minified for development

### 4. Image Optimization

**Use WordPress image sizes:**
```php
// Use thumbnail for previews, not full size
wp_get_attachment_image_src($attachment_id, 'thumbnail');
```

### 5. AJAX Optimization

**Use WordPress REST API:**
- Faster than admin-ajax.php
- Better for batch operations
- Cacheable responses

---

## 🧪 Testing Requirements

### Unit Tests (PHPUnit):
```php
// Test API manager
test_api_connection_success()
test_api_connection_failure()
test_generate_alt_text_anthropic()
test_generate_alt_text_openai()

// Test encryption
test_encrypt_decrypt_api_key()

// Test scanner
test_scan_finds_missing_alt_text()
test_scan_ignores_images_with_alt()

// Test batch processor
test_batch_processes_multiple_images()
test_batch_handles_failures()
```

### Integration Tests:
- Test full workflow: scan → generate → save
- Test with both API providers
- Test error handling
- Test batch processing

### Manual Testing Checklist:
- [ ] Install fresh on WordPress 5.8
- [ ] Install fresh on WordPress latest
- [ ] Test with 1,000+ images
- [ ] Test with slow API responses
- [ ] Test with API errors
- [ ] Test wizard flow
- [ ] Test settings save/load
- [ ] Test auto-generate on upload
- [ ] Test batch processing
- [ ] Test on mobile/tablet
- [ ] Test with different user roles
- [ ] Test plugin conflicts (common plugins)

---

## 📦 WordPress.org Submission

### readme.txt Template:

```
=== AI Alt Text Generator ===
Contributors: yourusername
Tags: accessibility, alt text, ai, images, seo
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate AI-powered alt text for your images. 100% free forever. Uses your own API key.

== Description ==

Make your WordPress site accessible with AI-generated alt text. This plugin helps you automatically generate descriptive alt text for all your images using AI.

**🎯 100% Free Forever**

This plugin is completely free with no paid tiers or hidden costs. You use your own API key from Anthropic or OpenAI, giving you full control and unlimited usage.

**✨ Features**

* Scan your entire media library
* Generate alt text for all images at once
* Auto-generate for new uploads
* Multiple tone options (Professional, Casual, Technical, SEO)
* Edit alt text before accepting
* Batch processing with progress tracking
* WooCommerce product image support
* Multilingual support
* Detailed usage statistics

**🚀 Easy Setup (3 Minutes)**

1. Install the plugin
2. Get a free API key from Anthropic or OpenAI
3. Add $5 credits (processes 6,000+ images with Anthropic)
4. Start generating alt text

**💰 Cost**

The plugin itself is free. You only pay for API usage:
* Anthropic Claude: ~$0.0008 per image ($5 = 6,000+ images)
* OpenAI GPT-4: ~$0.01 per image ($5 = 500 images)

Most users spend less than $10 total for their entire image library.

**🔒 Privacy & Security**

* API keys stored encrypted in your database
* No data sent to third parties (except AI providers)
* All processing happens on your server
* GDPR compliant

**🎓 Perfect For**

* Making sites accessible (WCAG compliance)
* Improving SEO with descriptive alt text
* Saving hours of manual work
* E-commerce product images
* Photography portfolios
* News and magazine sites

**💡 How It Works**

1. The plugin scans your media library
2. Sends images to your chosen AI provider (Anthropic or OpenAI)
3. AI generates descriptive alt text
4. You review and accept/edit
5. Alt text saved to your images

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-alt-text-generator/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Follow the setup wizard to add your API key
4. Start generating alt text!

**Detailed Setup Guide:**

1. Go to https://console.anthropic.com (or https://platform.openai.com)
2. Create a free account
3. Add $5 credits to your account
4. Generate an API key
5. Paste the key in the plugin setup wizard
6. Click "Test Connection"
7. Start generating!

== Frequently Asked Questions ==

= Is this really free? =

Yes! The plugin is 100% free with no paid tiers. You only pay for API usage to Anthropic or OpenAI, which costs around $0.0008-0.01 per image.

= Do I need coding knowledge? =

No! The setup wizard guides you through everything step-by-step with video tutorials.

= Which AI provider should I choose? =

We recommend Anthropic Claude for the best quality at the lowest cost ($0.0008/image). OpenAI is also great but more expensive ($0.01/image).

= How many images can I process? =

Unlimited! As long as you have credits in your API account, you can process as many images as you want.

= Will this work with WooCommerce? =

Yes! It works with WooCommerce product images and all other WordPress images.

= Can I edit the generated alt text? =

Absolutely! You can review, edit, or regenerate any alt text before accepting it.

= Is my API key safe? =

Yes. Your API key is encrypted and stored securely in your WordPress database. It's never sent anywhere except to the AI provider.

= What if I don't like the generated alt text? =

You can reject it, edit it, or regenerate it with a different tone. You have full control.

= Can I use this for multiple sites? =

Yes! Install it on as many sites as you want. Each site uses your API key.

= What languages are supported? =

Currently English. More languages coming soon!

== Screenshots ==

1. Setup wizard - Get started in 3 minutes
2. Dashboard - Overview of your images
3. Batch processing - Generate alt text for all images
4. Settings - Customize tone and behavior
5. Media library integration - Generate alt text right from media library
6. Review screen - Accept, edit, or reject generated alt text

== Changelog ==

= 1.0.0 =
* Initial release
* Anthropic Claude API support
* OpenAI GPT-4 Vision support
* Batch processing
* Auto-generate on upload
* Multiple tone options
* WooCommerce support
* Usage statistics

== Upgrade Notice ==

= 1.0.0 =
Initial release. Start making your site accessible today!
```

### Screenshot Requirements:

Create 6-8 screenshots showing:
1. Setup wizard (step-by-step)
2. Main dashboard
3. Batch processing in action
4. Settings page
5. Media library integration
6. Generated alt text preview
7. Before/after comparison
8. Mobile view

**Image specs:**
- Format: PNG or JPG
- Size: 1280×720 px (or larger)
- File size: < 1MB each
- Name: screenshot-1.png, screenshot-2.png, etc.

### Banner/Icon Assets:

**Plugin Banner:**
- banner-772x250.png (required)
- banner-1544x500.png (retina, recommended)
- Theme: Clean, modern, shows accessibility/AI concept
- Include plugin name and tagline

**Plugin Icon:**
- icon-128x128.png (required)
- icon-256x256.png (retina, recommended)
- Theme: Simple, recognizable, looks good at small sizes

---

## 📈 Marketing & Growth Strategy

### Launch Week (Day 1-7):

**Day 1: WordPress.org**
- Submit plugin
- Optimize readme.txt
- Upload all assets

**Day 2-3: Social Media**
- Reddit posts:
  - r/Wordpress: "I built a free AI alt text generator"