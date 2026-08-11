#!/usr/bin/env python3
"""Bake the home-banner copy INTO the artwork (PR #87's approach, rebuilt as a repo tool).

The storefront renders these banners full-bleed at aspect 3:1 with the swiper's
next-arrow overlaid near the right edge (x ~1533-1589 of this 1600px frame,
vertically centered) and pagination dots along the bottom. Copy must therefore
end left of SAFE_RIGHT — the 2026-08-10 walkthrough's "copy clips at 1440"
finding was two slides set to a 24-28px right margin, inside the arrow zone.

The script AUTOFITS: a slide whose longest line would cross SAFE_RIGHT gets its
type shrunk until it fits, and the run FAILS LOUDLY if it cannot. The safe zone
is enforced here, in the only place that draws, so the defect class cannot ship
again by hand-tuning.

Bases:  database/seeders/data/artwork-src/  (the pre-bake art, committed)
Output: database/seeders/data/artwork/banner-*.webp  (what ArtworkSeeder ships)
Fonts:  the app's own Newsreader variable font from node_modules
        (pip install fonttools brotli pillow)

Run:    python3 scripts/bake-banner-copy.py [output-dir]
After a copy change: re-run, `npm` not needed, commit the webp, deploy —
the seeder re-attaches changed files. BM never goes into pixels here; locale
copy lives in the banners table (subtitle/cta_label) and the img alt.
"""

import io
import sys
import tempfile
from pathlib import Path

from fontTools import ttLib
from fontTools.varLib import instancer
from PIL import Image, ImageDraw, ImageFont

REPO = Path(__file__).resolve().parent.parent
SRC = REPO / "database/seeders/data/artwork-src"
OUT = Path(sys.argv[1]) if len(sys.argv) > 1 else REPO / "database/seeders/data/artwork"
# The opsz file carries BOTH axes; opsz=72 is the display cut the shipped art
# uses — the wght-only file pins opsz at text size and renders ~25% wider.
FONT_WOFF2 = REPO / "node_modules/@fontsource-variable/newsreader/files/newsreader-latin-opsz-normal.woff2"
OPSZ = 72

CANVAS = (1600, 533)
ANCHOR_X = 905          # left edge of the copy block (clear of the product collage)
HEAD_TOP = 138
SAFE_RIGHT = 1500       # arrow zone starts ~1533; 100px matches the roomiest live slides
HEAD_SIZE = 92          # ideal; autofit shrinks per-slide when a line is too long
SUB_SIZE = 45
HEAD_RGB = (26, 49, 29)   # sampled from the shipped art (Souk deep green)
SUB_RGB = (94, 95, 87)    # sampled warm grey
HEAD_WGHT, SUB_WGHT = 500, 420

# file stem -> (headline lines, subline). Breaks are deliberate, not wrapped.
COPY = {
    "banner-verified-trade": (["A halal shop is not the", "same as a halal product"],
                              "The certificate is bound to the item."),
    "banner-groceries-pantry": (["Fill the pantry without", "second-guessing it"],
                                "From sellers verified before they list."),
    "banner-food-snacks": (["Snacks everyone at", "the table can eat"],
                           "Every packet traces to a certificate."),
    "banner-drinks": (["Know what is in", "the bottle"],
                      "Each under a certificate you can read."),
    "banner-care": (["Halal does not stop", "at the kitchen"],
                    "Checked the same way as the food."),
}


def static_font(weight: int) -> str:
    """Instantiate the variable woff2 at one weight, return a ttf path PIL can load."""
    font = ttLib.TTFont(FONT_WOFF2)
    instancer.instantiateVariableFont(font, {"wght": weight, "opsz": OPSZ}, inplace=True)
    font.flavor = None  # save as plain ttf
    out = tempfile.NamedTemporaryFile(suffix=f"-newsreader-{weight}.ttf", delete=False)
    font.save(out.name)
    return out.name


def fitted(draw: ImageDraw.ImageDraw, ttf: str, lines: list[str], size: int) -> ImageFont.FreeTypeFont:
    """Largest font <= size whose longest line ends inside SAFE_RIGHT."""
    while size > 24:
        f = ImageFont.truetype(ttf, size)
        widest = max(draw.textlength(line, font=f) for line in lines)
        if ANCHOR_X + widest <= SAFE_RIGHT:
            return f
        size -= 2
    raise SystemExit(f"cannot fit {lines!r} inside x={SAFE_RIGHT}")


def bake(stem: str, head_ttf: str, sub_ttf: str) -> str:
    base = SRC / f"{stem}.webp"
    im = Image.open(base).convert("RGB")
    if im.size != CANVAS:
        raise SystemExit(f"{base.name}: expected {CANVAS}, got {im.size}")
    draw = ImageDraw.Draw(im)

    head_lines, sub = COPY[stem]
    head = fitted(draw, head_ttf, head_lines, HEAD_SIZE)
    line_h = round(head.size * 1.22)  # measured off the shipped art (139->220 at 66px)
    y = HEAD_TOP
    for line in head_lines:
        draw.text((ANCHOR_X, y), line, font=head, fill=HEAD_RGB, anchor="la")
        y += line_h

    subf = fitted(draw, sub_ttf, [sub], SUB_SIZE)
    sub_y = y + 46
    draw.text((ANCHOR_X, sub_y), sub, font=subf, fill=SUB_RGB, anchor="la")

    # The invariant, measured on the actual render, not the plan.
    right = ANCHOR_X + max(
        max(draw.textlength(l, font=head) for l in head_lines),
        draw.textlength(sub, font=subf),
    )
    assert right <= SAFE_RIGHT, f"{stem}: text ends at {right:.0f} > {SAFE_RIGHT}"

    OUT.mkdir(parents=True, exist_ok=True)
    dest = OUT / f"{stem}.webp"
    im.save(dest, "WEBP", quality=88)
    return f"{stem}: head {head.size}px sub {subf.size}px, right edge {right:.0f} (margin {CANVAS[0]-right:.0f}px)"


def main() -> None:
    if not FONT_WOFF2.exists():
        raise SystemExit("Newsreader not found — run `npm install` first")
    head_ttf, sub_ttf = static_font(HEAD_WGHT), static_font(SUB_WGHT)
    for stem in COPY:
        print(bake(stem, head_ttf, sub_ttf))


if __name__ == "__main__":
    main()
