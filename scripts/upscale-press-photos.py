"""
Upscale press images 2.5× with high-quality resampling (Pillow).
Also installs logo + lecture assets into the mediakit folders.
"""
from pathlib import Path
from PIL import Image

ROOT = Path(r"c:\laragon\www\grippartner.nl\assets\media\press")
GEN = Path(r"C:\Users\korne\.cursor\projects\c-laragon-www-kornepot-nl\assets")

SCALE = 2.5  # 1024 -> ~2560


def upscale(src: Path, dst: Path, scale: float = SCALE) -> None:
    im = Image.open(src).convert("RGB")
    w, h = im.size
    out = im.resize((int(w * scale), int(h * scale)), Image.Resampling.LANCZOS)
    dst.parent.mkdir(parents=True, exist_ok=True)
    if dst.suffix.lower() in {".jpg", ".jpeg"}:
        out.save(dst, "JPEG", quality=92, optimize=True)
    else:
        out.save(dst, "PNG", optimize=True)
    print(f"{src.name}: {w}x{h} -> {out.size[0]}x{out.size[1]}  {dst.name}")


def crop_ratio(im: Image.Image, rw: float, rh: float, max_w: int) -> Image.Image:
    sw, sh = im.size
    target = rw / rh
    src_ratio = sw / sh
    if src_ratio > target:
        crop_h = sh
        crop_w = int(round(sh * target))
        x = (sw - crop_w) // 2
        y = 0
    else:
        crop_w = sw
        crop_h = int(round(sw / target))
        x = 0
        y = (sh - crop_h) // 3
    cropped = im.crop((x, y, x + crop_w, y + crop_h))
    out_w = min(max_w, crop_w)
    out_h = int(round(out_w / target))
    return cropped.resize((out_w, out_h), Image.Resampling.LANCZOS)


author_dir = ROOT / "author"
media_dir = ROOT / "media"
logo_dir = ROOT / "logos"
logo_dir.mkdir(parents=True, exist_ok=True)

# Upscale originals in place (write hi-res siblings then replace exports)
sources = [
    author_dir / "korne-pot-bookshelf-wide.png",
    author_dir / "korne-pot-office.png",
    author_dir / "korne-pot-bookshelf-close.png",
    author_dir / "korne-pot-bookshelf-door.png",
    media_dir / "korne-pot-podcast-studio.png",
    media_dir / "korne-pot-podcast-bookshelf.png",
    media_dir / "korne-pot-awardshow.png",
    media_dir / "korne-pot-award-tiktok.png",
]

hi_dir = ROOT / "hires"
hi_dir.mkdir(exist_ok=True)

for src in sources:
    if not src.is_file():
        print("missing", src)
        continue
    dst = hi_dir / (src.stem + "-hires.jpg")
    upscale(src, dst)

# Lecture (AI) + logo
lezing_src = GEN / "korne-pot-lezing.png"
logo_src = GEN / "grippartner-woordmerk.png"
if lezing_src.is_file():
    # Save large lecture asset
    im = Image.open(lezing_src).convert("RGB")
    # If already large enough keep; else upscale to min 2400 wide
    if im.size[0] < 2400:
        scale = 2400 / im.size[0]
        im = im.resize((2400, int(im.size[1] * scale)), Image.Resampling.LANCZOS)
    out = media_dir / "korne-pot-lezing.jpg"
    im.save(out, "JPEG", quality=92, optimize=True)
    print("lecture", im.size, "->", out.name)

if logo_src.is_file():
    # Transparent-ish: keep PNG
    im = Image.open(logo_src).convert("RGBA")
    # Upscale logo for crisp downloads
    if im.size[0] < 2000:
        scale = 2000 / im.size[0]
        im = im.resize((2000, int(im.size[1] * scale)), Image.Resampling.LANCZOS)
    out = logo_dir / "grippartner-woordmerk.png"
    im.save(out, "PNG", optimize=True)
    # Also simple SVG-like note: keep PNG as download (manifest expects svg optionally)
    print("logo", im.size, "->", out.name)

# Rebuild standard crops from hires
wide = Image.open(hi_dir / "korne-pot-bookshelf-wide-hires.jpg").convert("RGB")
close = Image.open(hi_dir / "korne-pot-bookshelf-close-hires.jpg").convert("RGB")
office = Image.open(hi_dir / "korne-pot-office-hires.jpg").convert("RGB")

crop_ratio(wide, 3, 2, 3000).save(author_dir / "korne-pot-landscape.jpg", "JPEG", quality=92, optimize=True)
crop_ratio(office, 2, 3, 2400).save(author_dir / "korne-pot-portrait.jpg", "JPEG", quality=92, optimize=True)
crop_ratio(close, 1, 1, 2000).save(author_dir / "korne-pot-square.jpg", "JPEG", quality=92, optimize=True)

# Also replace downloadable originals with hires JPGs for media shots
for name in [
    "korne-pot-podcast-studio",
    "korne-pot-podcast-bookshelf",
    "korne-pot-awardshow",
    "korne-pot-award-tiktok",
]:
    src = hi_dir / f"{name}-hires.jpg"
    if src.is_file():
        # keep png name but write jpg companion + overwrite png as large jpeg-in-png? better write .jpg and update manifest
        src.replace if False else None
        Image.open(src).save(media_dir / f"{name}.jpg", "JPEG", quality=92, optimize=True)
        print("media jpg", name)

# Homepage avatar
sq = Image.open(author_dir / "korne-pot-square.jpg")
img_dir = Path(r"c:\laragon\www\grippartner.nl\assets\img")
sq.save(img_dir / "korne-pot-author.jpg", "JPEG", quality=90, optimize=True)

# Report sizes
print("\n=== sizes ===")
for p in sorted(author_dir.glob("*")) + sorted(media_dir.glob("*")) + sorted(logo_dir.glob("*")):
    if p.suffix.lower() not in {".jpg", ".jpeg", ".png"}:
        continue
    im = Image.open(p)
    print(f"{im.size[0]}x{im.size[1]}  {p.stat().st_size//1024}KB  {p.relative_to(ROOT)}")
