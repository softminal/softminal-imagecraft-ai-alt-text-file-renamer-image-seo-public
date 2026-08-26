#!/usr/bin/env python3
"""
Render the ImageCraft WordPress.org banners (1544x500 and 772x250).

Keeps the 1.5.0 composition — mark left, headline centre, feature pills along
the bottom — because that layout already worked; what changes is the mark
(Bolt Horizon, same geometry as make-icon.py so banner and directory icon are
the same artwork), the pill set, and the tagline.

Everything is drawn at 2x and downsampled so the type and diagonals stay clean.
"""
import os
from PIL import Image, ImageDraw, ImageFont, ImageFilter

OUT = os.path.dirname(os.path.abspath(__file__))
SS = 2  # supersample

FONT = "/System/Library/Fonts/Avenir Next.ttc"
F_BOLD, F_DEMI, F_MED, F_REG = 0, 2, 5, 7

# Sampled from the 1.5.0 banner so the family resemblance holds.
BG_TL = (28, 63, 130)
BG_TR = (10, 20, 47)
BG_BL = (21, 33, 73)
BG_BR = (14, 21, 48)

WHITE = (255, 255, 255)
ACCENT = (147, 185, 255)      # "Image Toolkit" tint
MUTED = (150, 170, 205)
EYEBROW = (140, 165, 210)

# Bolt Horizon mark — identical geometry to make-icon.py.
MARK_RADIUS = 18.3
MOUNTAIN = [(8.0, 50.3), (21.7, 33.1), (30.9, 42.9), (40.0, 30.9), (56.0, 50.3)]
BOLT = [(46.9, 5.7), (25.7, 20.0), (43.4, 20.0), (41.7, 29.7), (51.4, 16.0), (45.1, 16.0)]
MARK_BLUE = (59, 130, 246)
MARK_TEAL = (13, 148, 136)

PILLS = [
    ("AI Alt Text",     (47, 91, 219)),
    ("SEO Rename",      (124, 77, 243)),
    ("Smart Compress",  (54, 125, 233)),
    ("Next-gen WebP",   (75, 192, 249)),
    ("Image Fallbacks", (30, 198, 148)),
]

TAGLINE = "AI alt text & titles, SEO renaming, compression & next-gen WebP."


def font(idx, size):
    return ImageFont.truetype(FONT, size, index=idx)


def bilinear_bg(size):
    """Four-corner colour blend + faint grid, matching the 1.5.0 background."""
    w, h = size
    small = Image.new("RGB", (2, 2))
    small.putpixel((0, 0), BG_TL)
    small.putpixel((1, 0), BG_TR)
    small.putpixel((0, 1), BG_BL)
    small.putpixel((1, 1), BG_BR)
    bg = small.resize((w, h), Image.BILINEAR)

    grid = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    g = ImageDraw.Draw(grid)
    step = round(w / 26)
    for x in range(0, w, step):
        g.line([(x, 0), (x, h)], fill=(255, 255, 255, 8), width=max(1, SS // 2))
    for y in range(0, h, step):
        g.line([(0, y), (w, y)], fill=(255, 255, 255, 8), width=max(1, SS // 2))
    bg = Image.alpha_composite(bg.convert("RGBA"), grid)

    # Soft glow behind the headline so the type separates from the field.
    glow = Image.new("L", (w, h), 0)
    gd = ImageDraw.Draw(glow)
    gd.ellipse([w * 0.22, -h * 0.55, w * 0.95, h * 1.05], fill=44)
    glow = glow.filter(ImageFilter.GaussianBlur(w * 0.07))
    tint = Image.new("RGBA", (w, h), (70, 120, 220, 0))
    tint.putalpha(glow)
    return Image.alpha_composite(bg, tint).convert("RGB")


def draw_mark(img, x, y, size):
    """Bolt Horizon tile: gradient rounded square + white horizon and bolt."""
    s = size
    tile = Image.new("RGB", (64, 64))
    px = tile.load()
    for j in range(64):
        for i in range(64):
            t = (i / 63 + j / 63) / 2
            px[i, j] = tuple(
                round(MARK_BLUE[k] + (MARK_TEAL[k] - MARK_BLUE[k]) * t) for k in range(3)
            )
    tile = tile.resize((s, s), Image.LANCZOS).convert("RGBA")

    mask = Image.new("L", (s, s), 0)
    ImageDraw.Draw(mask).rounded_rectangle(
        [0, 0, s - 1, s - 1], radius=round(MARK_RADIUS * s / 64), fill=255)

    d = ImageDraw.Draw(tile)
    k = s / 64.0
    d.polygon([(a * k, b * k) for a, b in MOUNTAIN], fill=WHITE)
    d.polygon([(a * k, b * k) for a, b in BOLT], fill=WHITE)

    # Drop shadow under the tile.
    shadow = Image.new("RGBA", img.size, (0, 0, 0, 0))
    shadow.paste((0, 0, 0, 90), (x, y + round(s * 0.06)), mask)
    shadow = shadow.filter(ImageFilter.GaussianBlur(s * 0.09))
    img.alpha_composite(shadow)
    img.paste(tile, (x, y), mask)


def pill(draw, img, x, y, label, colour, fnt, pad_x, pad_y, radius):
    tw = draw.textlength(label, font=fnt)
    ascent, descent = fnt.getmetrics()
    th = ascent + descent
    w = round(tw + pad_x * 2)
    h = round(th + pad_y * 2)

    chip = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    ImageDraw.Draw(chip).rounded_rectangle([0, 0, w - 1, h - 1], radius=radius,
                                           fill=colour + (255,))
    shadow = Image.new("RGBA", img.size, (0, 0, 0, 0))
    shadow.paste((0, 0, 0, 70), (x, y + round(h * 0.10)), chip.split()[3])
    shadow = shadow.filter(ImageFilter.GaussianBlur(h * 0.14))
    img.alpha_composite(shadow)
    img.alpha_composite(chip, (x, y))

    ImageDraw.Draw(img).text((x + pad_x, y + pad_y), label, font=fnt, fill=WHITE)
    return w


def render(width, height):
    W, H = width * SS, height * SS
    img = bilinear_bg((W, H)).convert("RGBA")
    d = ImageDraw.Draw(img)
    u = W / 1544.0 / SS * SS  # scale factor relative to the 1544 design

    def sc(v):
        return round(v * W / (1544 * SS) * SS)

    # --- mark (left) ---
    mark_size = sc(196)
    mark_x, mark_y = sc(112), (H - mark_size) // 2 - sc(26)
    draw_mark(img, mark_x, mark_y, mark_size)

    text_x = sc(392)

    # --- eyebrow ---
    f_eye = font(F_DEMI, sc(25))
    eyebrow = "IMAGECRAFT   •   BY SOFTMINAL"
    ex = text_x
    d.ellipse([ex, sc(72), ex + sc(13), sc(85)], fill=(90, 150, 255))
    d.text((ex + sc(28), sc(64)), eyebrow, font=f_eye, fill=EYEBROW)

    # --- headline ---
    f_h = font(F_BOLD, sc(80))
    d.text((text_x, sc(112)), "Your All-in-One", font=f_h, fill=WHITE)
    d.text((text_x, sc(196)), "Image Toolkit", font=f_h, fill=ACCENT)

    # --- tagline ---
    f_t = font(F_MED, sc(29))
    d.text((text_x, sc(300)), TAGLINE, font=f_t, fill=MUTED)

    # --- pills ---
    f_p = font(F_DEMI, sc(27))
    px_, py_ = sc(26), sc(15)
    gap = sc(20)
    widths = [round(d.textlength(l, font=f_p) + px_ * 2) for l, _ in PILLS]
    total = sum(widths) + gap * (len(PILLS) - 1)
    x = (W - total) // 2
    y = H - sc(118)
    for (label, colour), w in zip(PILLS, widths):
        pill(d, img, x, y, label, colour, f_p, px_, py_, sc(22))
        x += w + gap

    return img.convert("RGB").resize((width, height), Image.LANCZOS)


if __name__ == "__main__":
    for w, h in ((1544, 500), (772, 250)):
        p = os.path.join(OUT, "banner-%dx%d.png" % (w, h))
        render(w, h).save(p, "PNG", optimize=True)
        print("wrote", os.path.basename(p), os.path.getsize(p) // 1024, "KB")
