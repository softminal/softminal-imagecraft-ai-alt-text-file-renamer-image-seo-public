#!/usr/bin/env python3
"""
Render the ImageCraft "Bolt Horizon" mark to WordPress.org assets.

Outputs an animated icon-256x256.gif (the bolt strikes, briefly lighting the
horizon) plus static PNG icons. Everything is drawn at 4x and downsampled so
the diagonals stay clean.
"""
import math
import os
from PIL import Image, ImageDraw, ImageFilter

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "out")
os.makedirs(OUT, exist_ok=True)

SS = 4                      # supersample factor
BLUE = (59, 130, 246)       # #3B82F6
TEAL = (13, 148, 136)       # #0D9488

# Geometry in a 64-unit square that IS the icon (mark fills the canvas).
RADIUS = 18.3
MOUNTAIN = [(8.0, 50.3), (21.7, 33.1), (30.9, 42.9), (40.0, 30.9), (56.0, 50.3)]
BOLT = [(46.9, 5.7), (25.7, 20.0), (43.4, 20.0), (41.7, 29.7), (51.4, 16.0), (45.1, 16.0)]
BOLT_CENTER = (38.5, 17.5)


def gradient(size):
    """Diagonal blue -> teal, drawn per-pixel on a small tile then scaled."""
    tile = 64
    g = Image.new("RGB", (tile, tile))
    px = g.load()
    for y in range(tile):
        for x in range(tile):
            t = (x / (tile - 1) + y / (tile - 1)) / 2.0
            px[x, y] = (
                round(BLUE[0] + (TEAL[0] - BLUE[0]) * t),
                round(BLUE[1] + (TEAL[1] - BLUE[1]) * t),
                round(BLUE[2] + (TEAL[2] - BLUE[2]) * t),
            )
    return g.resize((size, size), Image.LANCZOS)


def scale(points, size):
    k = size / 64.0
    return [(x * k, y * k) for x, y in points]


def radial_glow(size, center, radius, strength):
    """Soft white halo, used for the strike flash."""
    g = Image.new("L", (size, size), 0)
    d = ImageDraw.Draw(g)
    steps = 14
    for i in range(steps, 0, -1):
        r = radius * (i / steps)
        a = int(255 * strength * (1 - i / steps) ** 1.6)
        cx, cy = center
        d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=a)
    return g.filter(ImageFilter.GaussianBlur(radius * 0.22))


def render(size, bolt_bright=0.0, horizon_bright=0.0, glow=0.0, bolt_dim=0.0,
           flash=0.0):
    """One frame. Brightness args are 0..1."""
    s = size * SS
    base = gradient(s)

    # rounded-square alpha mask
    mask = Image.new("L", (s, s), 0)
    ImageDraw.Draw(mask).rounded_rectangle(
        [0, 0, s - 1, s - 1], radius=RADIUS * (s / 64.0), fill=255
    )

    # lightning lights the whole scene, not just its own halo
    if flash > 0:
        base = Image.blend(base, Image.new("RGB", (s, s), (255, 255, 255)),
                           0.30 * flash)

    img = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    img.paste(base, (0, 0), mask)

    # strike halo behind the shapes, clipped to the rounded square
    if glow > 0:
        cx, cy = BOLT_CENTER
        halo = radial_glow(s, (cx * s / 64.0, cy * s / 64.0), s * 0.46, glow)
        halo = Image.composite(halo, Image.new("L", (s, s), 0), mask)
        img = Image.alpha_composite(img, Image.merge("RGBA", (
            Image.new("L", (s, s), 255), Image.new("L", (s, s), 255),
            Image.new("L", (s, s), 255), halo,
        )))

    d = ImageDraw.Draw(img)

    # horizon: white at rest, lifted toward pure white on the strike
    hv = 255
    ha = int(245 + 10 * horizon_bright)
    d.polygon(scale(MOUNTAIN, s), fill=(hv, hv, hv, ha))

    # bolt: dips slightly on the charge frame, blows out on the strike
    bv = 255
    ba = int(255 - 55 * bolt_dim)
    d.polygon(scale(BOLT, s), fill=(bv, bv, bv, ba))

    if bolt_bright > 0:
        b = radial_glow(s, (BOLT_CENTER[0] * s / 64.0, BOLT_CENTER[1] * s / 64.0),
                        s * 0.20, bolt_bright)
        img = Image.alpha_composite(img, Image.merge("RGBA", (
            Image.new("L", (s, s), 255), Image.new("L", (s, s), 255),
            Image.new("L", (s, s), 255), b,
        )))
        d = ImageDraw.Draw(img)
        d.polygon(scale(BOLT, s), fill=(255, 255, 255, 255))

    return img.resize((size, size), Image.LANCZOS)


def to_paletted(rgba):
    """RGBA -> P with a reserved transparent index, so corners stay clean."""
    rgb = rgba.convert("RGB")
    p = rgb.quantize(colors=127, method=Image.Quantize.MEDIANCUT)
    holes = rgba.getchannel("A").point(lambda a: 255 if a < 128 else 0)
    p.paste(255, holes)
    pal = p.getpalette() or []
    pal = (pal + [0] * 768)[:768]
    p.putpalette(pal)
    p.info["transparency"] = 255
    return p


# --- animation: long rest, quick double-pulse strike, settle ------------------
# WordPress.org serves icon-128x128 to standard-DPI screens and icon-256x256 to
# retina, so BOTH must be the animated GIF — otherwise the icon animates on some
# displays and sits still on others.
timeline = [
    (dict(), 1700),                                                            # rest
    (dict(bolt_dim=1.0), 90),                                                  # charge
    (dict(bolt_bright=0.9, horizon_bright=1.0, glow=0.6, flash=1.0), 70),      # strike
    (dict(bolt_bright=0.4, horizon_bright=1.0, glow=0.3, flash=0.35), 70),     # flare
    (dict(bolt_bright=0.75, horizon_bright=1.0, glow=0.5, flash=0.8), 60),     # 2nd pulse
    (dict(bolt_bright=0.25, horizon_bright=0.5, glow=0.2, flash=0.28), 80),    # decay
    (dict(glow=0.08, flash=0.08), 100),                                        # settle
]

def build_gif(size):
    """Render the timeline at `size` and write icon-<size>x<size>.gif."""
    frames = [to_paletted(render(size, **kwargs)) for kwargs, _ in timeline]
    durations = [ms for _, ms in timeline]
    path = os.path.join(OUT, "icon-%dx%d.gif" % (size, size))
    frames[0].save(
        path, save_all=True, append_images=frames[1:], duration=durations,
        loop=0, disposal=2, optimize=False,
    )
    return path, len(frames)


# Only ONE format per icon size ships to WP.org — the animated GIF. Emitting a
# PNG at the same size too would leave the directory two candidates for the
# same slot with no documented precedence between them.
gifs = [build_gif(px) for px in (256, 128)]

# mark-64.png is not a directory icon — it is the in-plugin/marketing mark.
render(64).save(os.path.join(OUT, "mark-64.png"))

for path, n in gifs:
    print(os.path.basename(path) + ":", os.path.getsize(path), "bytes,", n, "frames")
for f in sorted(os.listdir(OUT)):
    print(" ", f, os.path.getsize(os.path.join(OUT, f)), "bytes")
