# How to Add AI Alt Text to WooCommerce Product Images (in Bulk)

*By the ImageCraft Team · Updated July 2026*

Open your WooCommerce store's Media Library and look at the product photos. Now imagine you can't see any of them. What would you know about each one? For most stores, the answer is nothing. The images have no alt text. There is no short line of words that says what the photo shows.

That blank alt text costs you in two ways.

First, shoppers who use a screen reader hear nothing useful. The screen reader reaches the image and either says the file name or stays silent. So a blind shopper on your product page has no idea what the main photo shows. That is a real barrier to a sale, and in many places it is also a legal risk.

Second, Google can't see your image either. It reads the alt text to understand the picture. No alt text means no clear signal, and a weaker chance of showing up in Google Images. For a store, that is lost free traffic, because a lot of product searches start with pictures.

Adding alt text by hand fixes both problems. But writing it for a few hundred products, with several images each, takes days. That is why most stores never do it.

ImageCraft does it for you, and it does it well. The key is that it reads your WooCommerce catalog first. It knows each product's name, SKU, categories, and price before it writes a single word. So the alt text is about the real product, not a plain guess from the photo. You get "Tan leather crossbody bag, Aria Collection" instead of "a brown bag on a table."

This guide walks through the whole thing. You will set it up once, then fix your entire store's alt text, titles, and descriptions. It covers featured images and gallery images, all from one screen. By the end you will also know how to keep new products optimized without any extra work.

---

## Table of Contents

1. [TL;DR](#tldr)
2. [A quick word on what alt text is](#what-is-alt-text)
3. [Why product alt text is better than plain AI alt text](#why-it-works)
4. [Setup: two minutes](#setup)
5. [Method 1: Do the whole catalog at once (recommended)](#method-1)
6. [Method 2: One product at a time](#method-2)
7. [Method 3: Turn on auto-generate](#method-3)
8. [Pick the right product tone](#tones)
9. [Titles and descriptions, not just alt text](#titles)
10. [Which method should you use?](#decision)
11. [A few habits that keep your store clean](#habits)
12. [FAQ](#faq)
13. [Conclusion](#conclusion)

---

<a name="tldr"></a>
## TL;DR

- ImageCraft has a **WooCommerce screen** that lists your products. You can filter by missing, generic, or too-long alt text, titles, and descriptions.
- It sends the **product name, SKU, categories, and price** to the AI. So the alt text is about the real product, not a guess from the photo.
- **To do it in bulk:** open **ImageCraft → WooCommerce**, filter to *Missing* alt text, click **Select All**, tick the fields you want, and click **Generate**. Featured and gallery images are both included.
- **To keep it done:** turn on auto-generate. Every new product image then gets alt text on upload.
- Pick a **product tone**: SEO, Descriptive, or Lifestyle.
- ImageCraft uses **your own AI key** (Anthropic, OpenAI, or Gemini). You pay the provider directly. No credits, no markup.

---

<a name="what-is-alt-text"></a>
## A quick word on what alt text is

If you already know what alt text is, skip ahead. If not, here is the short version.

Alt text, short for "alternative text," is a line of words attached to an image. You don't see it on the page in normal use. It sits in the image's code. Two things read it.

The first is a screen reader. This is the software a blind or low-vision person uses to hear a web page out loud. When it reaches an image, it reads the alt text so the person knows what the picture shows.

The second is a search engine. Google can't truly "see" a photo the way you do. It leans on the alt text to work out what the image is about. Good alt text helps that image show up when people search.

So alt text serves people and search at the same time. One short line does both jobs. The trick is that the line has to be accurate and specific. "Image123" helps no one. "Tan leather crossbody bag, Aria Collection" helps both.

---

<a name="why-it-works"></a>
## Why product alt text is better than plain AI alt text

Most AI alt-text tools only look at the image. They describe a hoodie photo as "a black hoodie on a white background." That is correct, but it does not help you sell. It has no product name, no model, and none of the words a buyer would type into search.

ImageCraft works differently. Before it writes anything, it reads the product in WooCommerce and passes the details to the AI. It also tells the AI that the alt text must include the product name.

Here is the same hoodie photo, two ways:

- **Plain AI:** `a black hoodie on a white background`
- **ImageCraft, Product SEO tone:** `Teton black cotton pullover hoodie, Men's Hoodies & Sweatshirts`

The difference is not small. The second version names the product. It uses the words a buyer would search. And it repeats the product name that Google already links to that page, which makes the whole page a stronger match.

There is one more benefit that is easy to miss. ImageCraft saves the alt text to the Media Library, on the image itself. It does not save it to a single block or a single page. So the alt text follows the image everywhere it is used: the shop page, the product page, related products, a hero block, a slider, or any theme that reads the image. You do the work once, and every spot that uses the image gets the benefit.

---

<a name="setup"></a>
## Setup: two minutes

You only do this once.

1. **Install ImageCraft** from the WordPress plugin directory and activate it. If WooCommerce is active, ImageCraft finds it on its own.
2. **Add your AI key.** Go to **ImageCraft → Settings → API**. Pick a provider (Anthropic Claude, OpenAI, or Google Gemini) and paste your key. No key? On WordPress 7.0 and up you can use the built-in AI instead.
3. **Turn on product context.** Go to **ImageCraft → Settings → Integrations → WooCommerce** and check **Include product information**. This is what feeds the product name, categories, and SKU into each generation. This one checkbox is the whole reason the alt text comes out product-specific, so don't skip it.
4. **Pick a default tone** on the same screen. Start with **Product SEO (Recommended)**. More on tones [below](#tones).

Now you are ready. The rest of this guide is about which path to take through your catalog.

---

<a name="method-1"></a>
## Method 1: Do the whole catalog at once (recommended)

**Best for:** stores that never added alt text, or that just imported a lot of products.

This is the fastest way to fix a whole store. If you only read one section, read this one.

### Steps

![ImageCraft WooCommerce Products screen with products filtered by missing alt text; each card shows an alt-text coverage badge and View Details and Generate All buttons](beauty-woocommerce-products.png)

1. Go to **ImageCraft → WooCommerce**. Your products show up as cards, with a filter bar at the top. Each card carries an alt-text coverage badge — like **0/1 alt** or **3/3 alt** — so you can see at a glance which products still need work.
2. **Filter to what needs work.** Under **Alt Text**, click **Missing** to show only products with no alt text. You can also filter by **Generic** (weak placeholder text) or **Long >100** (text past your length limit). The same filters exist for **Title** and **Description**.
3. *(Optional)* **Narrow it down.** Use the category dropdown to work one collection at a time, or open **Advanced** to filter by date added or price range. On a large store, working in smaller groups is easier to check and easier to redo if you change a setting.
4. Click **Select All**. If your filter has more products than fit on one page, ImageCraft offers to select all of them across every page, not just the ones you can see.
5. **Pick the fields.** Tick **Alt text**, **Title**, and **Description** in any mix. One AI call per image fills in whatever you ticked, so ticking all three is not much slower than ticking one.
6. Click **Generate**. ImageCraft gathers every image, both featured and gallery, removes duplicates, and starts a background job.
7. Watch the progress on the Batch screen. You can close the tab and walk away. The job keeps running, and when you come back it shows where it got to. If it was interrupted, it picks up where it left off instead of starting over.

### What to expect

The job runs in the background, so your store stays fast the whole time. Speed depends on your AI provider and how many images you sent, but a few hundred images is usually a matter of minutes, not hours.

> **Tip:** One job handles up to **500 images**. For a big catalog, filter by category or price and run a few jobs in a row. Each new job starts where the last one ended, so you can clear a large store in a handful of passes.

---

<a name="method-2"></a>
## Method 2: One product at a time

**Best for:** a product you just published, or a top seller where you want to read the wording before it goes live.

Method 1 is great for volume. But sometimes you want a closer look, one product at a time.

![ImageCraft product details window for a hoodie, showing the featured and gallery images, each with alt text, title, and description fields and a Generate link, plus a Save All Changes button](beauty-woocommerce-product-modal.png)

1. Go to **ImageCraft → WooCommerce** and find the product card.
2. Click **Generate All** on the card. This writes alt text for the featured image and every gallery image in one click. Good for when you trust the output and just want it done.
3. Or click **View Details** to open the product window. The featured image (marked with a **Featured** badge) and every gallery image sit side by side, each with its own alt text, title, and description fields. Use the **Generate** link on any field to fill just that one, edit anything by hand, then click **Save All Changes**. Good for a flagship product where the words matter.

> **Why this helps stores:** gallery images are the ones most often left blank. The featured image sometimes gets attention. The other gallery shots almost never do. Generate All covers the whole set in one click, so those extra angles finally carry their weight in search.

---

<a name="method-3"></a>
## Method 3: Turn on auto-generate

**Best for:** a store that keeps growing, without extra work.

The two methods above clean up what you already have. This one keeps you clean going forward.

Turn on **auto-generate on upload** in the settings. After that, every image you add to a product gets alt text as it uploads. Add a new product, and its photos are already optimized by the time you hit publish. You never build up a backlog again, and you never have to remember to run a batch.

> **Note:** You stay in control. You can skip certain post types, and you can skip images that already have alt text. So ImageCraft only fills the gaps and leaves your own writing alone.

---

<a name="tones"></a>
## Pick the right product tone

Not every store wants the same style of alt text. A tone tells the AI what kind of description to write. ImageCraft has three WooCommerce tones, plus a custom option. Choose by your goal.

| Tone | Best for | Example |
|------|----------|---------|
| **Product SEO** *(recommended)* | Ranking in Google Images; keyword-rich text | `Teton black cotton pullover hoodie, Men's Hoodies & Sweatshirts` |
| **Product Descriptive** | Accessibility; clear, plain text for screen readers | `Black pullover hoodie with a drawstring hood and front kangaroo pocket` |
| **Product Lifestyle** | In-use or on-model photos | `Person wearing the black Teton pullover hoodie with the hood up` |

If you are not sure, start with **Product SEO**. It is a good middle ground for most stores. It names the product and uses search-friendly words, and it still reads fine for a screen reader.

Set your default under **Settings → Integrations → WooCommerce → Default Tone for Products**. Want your own wording? Choose **Custom Prompt** and write your own instructions. Even then, the product name, category, and SKU are still added as context, and your max-length limit still applies. So a custom prompt gives you your voice without giving up the product detail.

---

<a name="titles"></a>
## Titles and descriptions, not just alt text

Alt text gets the spotlight, but it is not the only text field on an image. Each image in WordPress also has a **title** and a **description** (caption). ImageCraft can write those too.

Why bother? A clear title makes images easier to find and manage inside your own Media Library, which matters once you have thousands of them. Descriptions can add extra context that some themes and galleries display under the image.

The nice part is that it costs you almost nothing to add them. Because ImageCraft makes one AI call per image, ticking **Title** and **Description** alongside **Alt text** fills all three in the same pass. You are not paying for three separate runs. So if you are already generating alt text for the whole store, it is worth ticking the other two boxes at the same time.

---

<a name="decision"></a>
## Which method should you use?

| Your situation | Best method | Do this now |
|----------------|-------------|-------------|
| Store with lots of missing alt text | **Method 1** | Filter to *Missing*, Select All, Generate |
| Big catalog (thousands of images) | **Method 1, by category** | Run one job per category |
| Just published one product | **Method 2** | Click *Generate All* on the card |
| Want to read the wording first | **Method 2, View Details** | Open the window, generate per field, then Save All Changes |
| Want it handled from now on | **Method 3** | Turn on auto-generate |

Most stores end up using two of these together. Run Method 1 once to clear the backlog, then turn on Method 3 so it never comes back.

---

<a name="habits"></a>
## A few habits that keep your store clean

Once your catalog is done, a little upkeep keeps it that way.

- **Leave auto-generate on.** It is the single best habit. New products stay optimized on their own.
- **Check the Missing filter now and then.** After a big import or a bulk product edit, pop into the WooCommerce screen and filter to *Missing* to catch anything that slipped through.
- **Read a few results after your first run.** Skim ten or so images to make sure the tone fits your brand. If it feels off, switch the tone or write a custom prompt, then run again.
- **Skip images that already have alt text.** If you have written some by hand, turn on the skip setting so ImageCraft fills gaps and leaves your work untouched.
- **Don't forget galleries.** When you add a product, add its gallery images before you generate, so they get done in the same pass.

None of these take long. Together they mean you almost never have to think about image alt text again.

---

<a name="faq"></a>
## FAQ

**Does it cover gallery images, or just the featured image?**
Both. Bulk **Generate** and per-card **Generate All** collect the featured image and every gallery image, with duplicates removed. Nothing gets missed.

**How is this different from a normal AI alt-text plugin?**
ImageCraft sends your real WooCommerce data (product name, SKU, categories, price) to the AI, and tells it to include the product name. Normal tools only see the photo, so their text is vague.

**Will it overwrite alt text I wrote myself?**
Only if you want it to. Filter to *Missing* to touch empty images only, and turn on "skip images with existing alt text" to fill gaps without changing your work.

**Can it write titles and descriptions too?**
Yes. When you bulk-generate, tick **Alt text**, **Title**, and **Description**. One AI call per image handles the fields you pick, so there is no extra cost to adding them.

**Do I need ImageCraft Pro for this?**
No. The WooCommerce screen, product alt text, gallery generation, and the three tones are all in the free plugin. The free plan gives you 50 actions per day. Pro removes the daily limit and adds features like auto-compress on upload.

**Which AI provider should I use?**
Use your own key from Anthropic Claude, OpenAI, or Google Gemini. Any of them works well. You pay the provider directly, with no markup. If you are not sure, pick the one you already have an account with.

**Will it slow down my store?**
No. The work runs in a background job from the dashboard and saves to the Media Library. It adds nothing to your storefront's load time. For faster pages, use ImageCraft's built-in image compression too.

**What happens if I close the browser during a big job?**
The job keeps running in the background. When you return to the Batch screen, it shows the current progress. If it was interrupted, it resumes instead of starting over.

**Is there a limit on how many images I can do at once?**
One job handles up to 500 images. For larger catalogs, split the work by category or price and run several jobs in a row.

---

<a name="conclusion"></a>
## Conclusion

Product photos are the biggest missed SEO win in most WooCommerce stores. Every image with no alt text, or with vague alt text, is lost image-search traffic and a barrier for shoppers who use screen readers. It is a big job to fix by hand, which is exactly why it stays undone.

ImageCraft closes that gap in minutes. Because it reads your catalog, the alt text is about the real product, not a guess from the pixels. Fix the whole store in one pass with Method 1. Handle new products one click at a time with Method 2. Or turn on auto-generate and forget about it with Method 3. Most stores use the first and the last together: clear the backlog once, then keep it clear on its own.

**Get started:** install ImageCraft, add your AI key in **Settings → API**, turn on **Include product information** under **Settings → Integrations → WooCommerce**, then open the **WooCommerce** screen, filter to *Missing*, and click **Generate**. Your product images will be ready for search before your next coffee.

---

*ImageCraft writes AI alt text, SEO file names, titles, and descriptions for your images, and compresses them without losing quality. It runs from your WordPress dashboard and uses your own Anthropic, OpenAI, or Gemini key.*
