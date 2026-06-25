# Spectra + ImageCraft: How to Optimize Spectra Image Blocks for SEO, Accessibility & Speed

*By the ImageCraft Team · Updated June 2026*

Your Spectra layout looks beautiful — but every Image block in it may be quietly dragging down your SEO and page speed. Empty alt text, file names like `IMG_4521.jpg`, and 4&nbsp;MB uploads are invisible in the editor, yet search engines and screen readers see them every time.

**ImageCraft fixes that.** It works at the **WordPress Media Library** level — exactly where the Spectra Image block pulls its images from — so when you optimize an image once with ImageCraft, every Spectra block that uses it inherits accessible alt text, an SEO-friendly file name, and a smaller, faster-loading file. Automatically.

In this guide you'll learn how to pair the two — generating AI alt text, SEO file names, and visually-lossless compression **before** (or after) you drop an image into a Spectra Image block.

---

## Table of Contents

1. [TL;DR](#tldr)
2. [Why Spectra + ImageCraft works so well](#why-it-works)
3. [Method 1: Optimize first, then insert into Spectra (recommended)](#method-1)
4. [Method 2: Already built the page? Optimize existing images](#method-2)
5. [Method 3: One-click compress from the media modal](#method-3)
6. [Which method should you use?](#decision)
7. [FAQ](#faq)
8. [Conclusion](#conclusion)

---

<a name="tldr"></a>
## TL;DR

- **Spectra's Image block reads from the Media Library** — so optimize the image *there* with ImageCraft, and every Spectra block using it benefits.
- **Best workflow:** upload → let ImageCraft auto-generate alt text → rename the file for SEO → compress → *then* insert the Spectra Image block.
- **Already published?** Run ImageCraft's **Scanner** to find images with missing alt text and fix them in bulk. Spectra blocks update with no re-editing.
- **For most sites:** turn on **auto-generate alt text on upload** and you'll never insert a Spectra image with empty alt text again.
- ImageCraft is **BYOK** (bring your own Anthropic, OpenAI, or Gemini key) and runs alt text, file renaming, and compression all from one dashboard.

---

<a name="why-it-works"></a>
## Why Spectra + ImageCraft works so well

It helps to understand one key thing about how the Spectra Image block works:

> **The Spectra Image block does not store its own copy of an image.** It references an attachment in your WordPress Media Library and pulls the image's `alt` text, file URL, and size from there.

This is great news, because it means you don't optimize images *inside* Spectra — you optimize them **once** in the Media Library, and Spectra reflects the result. ImageCraft is built precisely around the Media Library:

- **AI alt text** — generates descriptive, accessible alt text using Claude, OpenAI, or Gemini.
- **SEO file renaming** — turns `DSC_0099.jpg` into `red-leather-handbag.jpg`.
- **Image compression** — shrinks file size with visually-lossless quality, no plugin lock-in.

When the Spectra Image block then renders, it uses that alt text for accessibility and SEO, the clean file URL, and the smaller compressed file for faster loading. You do the work once; every block benefits.

---

<a name="method-1"></a>
## Method 1: Optimize first, then insert into Spectra (recommended)

**Best for:** new pages and posts you're building now.

This is the cleanest workflow — get the image right in the Media Library, then build your Spectra layout on top of polished assets.

### What this covers
- ✅ Alt text written before the image is ever placed
- ✅ Clean, keyword-rich file name baked into the URL
- ✅ Smaller file size for faster Spectra page loads

### Step-by-step

1. **Upload your image** to the Media Library (Media → Add New, or drag it in while editing).
2. **Turn on auto-generate alt text.** In **ImageCraft → Settings → Alt Text**, enable *Auto-generate on upload*. Now every new upload gets AI alt text automatically — no extra clicks.
3. **Rename the file for SEO.** Open **ImageCraft → Scanner**, find your image, and click **Rename**. ImageCraft suggests an SEO-friendly file name (you can edit it). This updates the file and its URL safely.
4. **Compress the image.** Click **Compress** on the same image (or use the media modal button — see Method 3). ImageCraft strips bloat and downscales oversized images while preserving visual quality, and keeps a backup so you can restore the original anytime.
5. **Now add the Spectra Image block.** In the block editor, add a **Spectra → Image** block and select your freshly optimized image from the Media Library. The alt text, clean file name, and compressed file all come along automatically.

> **Pro tip:** Want to fine-tune the wording? In **ImageCraft → Settings → Alt Text** you can choose a *tone* (professional, SEO, casual, technical) and a max character length. The Spectra Image block will display whatever alt text ImageCraft saved to the attachment.

---

<a name="method-2"></a>
## Method 2: Already built the page? Optimize existing images

**Best for:** live pages where Spectra Image blocks are already published with missing or weak alt text.

You don't need to touch the Spectra blocks at all. Because they reference the Media Library, fixing the image there updates the block automatically.

### Step-by-step

1. Go to **ImageCraft → Scanner**.
2. ImageCraft scans your Media Library and flags every image with **missing or empty alt text** — including all the images currently used in your Spectra blocks.
3. Select the images you want (or **Select All**) and click **Generate**.
4. Review the AI suggestions, then **Accept** them in bulk. The alt text is written back to each attachment.
5. **Reload your page.** The Spectra Image blocks now show the new alt text — no block re-editing needed.

> **Pro tip:** Use the **Batch** screen for very large libraries. It processes hundreds of images in the background so you can fix an entire site's worth of Spectra images in one run.

---

<a name="method-3"></a>
## Method 3: One-click compress from the media modal

**Best for:** when you're already in the media picker choosing an image for a Spectra block.

ImageCraft adds a **Compress** button right inside the WordPress media modal — the same modal Spectra opens when you click "Select Image."

### Step-by-step

1. In your Spectra Image block, click **Select Image** (or **Replace**).
2. Pick the image in the media modal.
3. In the attachment details panel on the right, click **Compress**.
4. ImageCraft compresses the file in place and keeps a backup. Insert the image into your Spectra block as usual.

> **Note:** The Compress button appears only when image compression is enabled in **ImageCraft → Settings → Compression**. The free plan includes manual single + bulk compression with a daily limit; ImageCraft Pro removes the cap and unlocks auto-compress on upload and adjustable quality.

---

<a name="decision"></a>
## Which method should you use?

| Your situation | Best method | Do this now |
|----------------|-------------|-------------|
| Building a new page with Spectra | **Method 1** | Turn on auto-alt-text, then rename + compress before inserting |
| Page is already live with empty alt text | **Method 2** | Run the Scanner and bulk-generate alt text |
| You're mid-edit in the media picker | **Method 3** | Click Compress in the media modal |
| Hundreds of images across many Spectra pages | **Method 2 (Batch)** | Use the Batch screen for background processing |

---

<a name="faq"></a>
## FAQ

**Does ImageCraft change the Spectra block itself?**
No. ImageCraft only optimizes the underlying Media Library attachment (alt text, file name, file size). The Spectra Image block simply reflects those values when it renders, so there's nothing to re-edit inside Spectra.

**Will renaming a file break the image in my Spectra block?**
No. ImageCraft updates the attachment's file and URL references safely. After a rename, your Spectra block continues pointing at the correct (new) URL.

**Do I need to re-insert images after generating alt text?**
No. Since Spectra reads alt text live from the attachment, accepting AI alt text in ImageCraft updates what the block outputs automatically.

**Does this work with Spectra galleries and other image-based blocks?**
Yes. Any block that pulls from the Media Library — Spectra's Image, gallery, and container background images — benefits from the optimized attachments.

**Do I need ImageCraft Pro?**
No. Alt text generation, the Scanner, SEO file renaming, and manual compression are all in the free plugin. Pro adds auto-compress on upload, adjustable compression quality, and removes daily limits.

**Which AI provider should I use?**
ImageCraft is BYOK — bring your own Anthropic Claude, OpenAI, or Google Gemini key. Any of them produces high-quality alt text; pick whichever you already have access to.

---

<a name="conclusion"></a>
## Conclusion

A polished Spectra layout deserves polished images underneath it. The two common problems — Spectra Image blocks shipping with **empty alt text** and **heavy, badly-named files** — both come down to the Media Library, and that's exactly where ImageCraft works.

Optimize once, and every Spectra block that uses the image inherits accessible alt text, an SEO-friendly URL, and a faster-loading compressed file. For new pages, optimize before you insert (Method 1). For existing pages, run the Scanner and fix everything in bulk (Method 2) — with zero block re-editing.

**Get started:** Install ImageCraft from the WordPress plugin directory, add your AI key in **Settings → API**, and turn on auto-generate alt text. Your next Spectra Image block will be SEO-ready the moment you insert it.

---

*ImageCraft generates AI-powered alt text, SEO-friendly file names, and visually-lossless image compression — all from your WordPress dashboard. Bring your own Anthropic, OpenAI, or Gemini key.*
