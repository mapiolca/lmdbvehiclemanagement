"""Create publication copies from reviewed screenshots; never overwrite sources.

Run with the bundled Python runtime (Pillow). Rectangles are pixel coordinates
in the source image. Only the listed regions are blurred; other decoded pixels
are checked unchanged. PNG outputs contain no source EXIF/GPS metadata.
"""
from pathlib import Path
import hashlib
import json
from PIL import Image, ImageChops, ImageDraw, ImageFilter

ROOT = Path(__file__).resolve().parent
MODULE = ROOT.parent.parent
OUT = ROOT / "images"

# Reviewed against each original illustration, including reference columns.
OLD_REGIONS = {
    "01-liste": [(396, 175, 481, 198), (645, 175, 732, 198)],
    "02-fiche": [(120, 25, 220, 50), (197, 129, 281, 151)],
    "04-affectations": [(120, 25, 220, 50)],
    "05-kilometrage": [(120, 25, 220, 50)],
    "07-synthese": [(37, 369, 119, 396), (37, 424, 119, 447)],
    "08-echeancier": [(334, 201, 420, 225), (334, 264, 420, 286)],
    "09-qualification": [(120, 25, 220, 50)],
    "10-controle": [(323, 70, 405, 94), (323, 107, 405, 130)],
    "11-assurance": [(818, 287, 901, 313)],
    "13-intervention-facture": [(218, 185, 302, 209)],
    "15-modeles": [(992, 378, 1075, 400)],
    "17-historique": [(120, 25, 220, 50)],
    "19-apercu-pdf": [(680, 55, 758, 77), (285, 193, 354, 215), (285, 216, 354, 237)],
}


def anonymize(source, target, regions, crop=None, restore=()):
    with Image.open(source) as opened:
        original = Image.new("RGB", opened.size)
        original.paste(opened.convert("RGB"))
    result = original.copy()
    mask = Image.new("L", original.size)
    painter = ImageDraw.Draw(mask)
    for box, radius in regions:
        x1, y1, x2, y2 = box
        assert 0 <= x1 < x2 <= original.width
        assert 0 <= y1 < y2 <= original.height
        result.paste(original.crop(box).filter(ImageFilter.GaussianBlur(radius)), box)
        painter.rectangle((x1, y1, x2 - 1, y2 - 1), fill=255)
    for box in restore:
        result.paste(original.crop(box), box)
        painter.rectangle((box[0], box[1], box[2] - 1, box[3] - 1), fill=0)
    difference = ImageChops.difference(original, result)
    difference.paste((0, 0, 0), mask=mask)
    assert difference.getbbox() is None, "Unexpected edit outside redaction regions"
    if crop:
        result = result.crop(crop)
    result.save(target, format="PNG", optimize=True)
    assert target.stat().st_size < 2 * 1024 * 1024, "Wiki upload limit"
    with Image.open(target) as check:
        assert not check.getexif() and not check.info, "Unexpected metadata"
    return {
        "source": source.relative_to(MODULE).as_posix(),
        "file": target.name,
        "size": list(result.size),
        "regions": [{"box": list(b), "radius": r} for b, r in regions],
        "crop": crop,
        "restore": list(restore),
        "sha256": hashlib.sha256(target.read_bytes()).hexdigest(),
        "outside_regions_unchanged": True,
        "metadata_removed": True,
    }


def main():
    OUT.mkdir(exist_ok=True)
    records = []
    mapping = {}
    for suffix, boxes in OLD_REGIONS.items():
        source = MODULE / "doc/wiki-1.0.0" / ("LMDBVehicleManagement-1.0.0-" + suffix + ".jpg")
        target = OUT / ("LMDBVehicleManagement-2026-09-" + suffix + "-anonymise.png")
        records.append(anonymize(source, target, [(b, 12) for b in boxes]))
        mapping[source.name] = target.name
    new_manifest = ROOT / "new-screenshots.json"
    if new_manifest.exists():
        for spec in json.loads(new_manifest.read_text(encoding="utf-8")):
            records.append(anonymize(
                MODULE / spec["source"], OUT / spec["file"],
                [(r["box"], r["radius"]) for r in spec["regions"]],
                spec.get("crop"), spec.get("restore", []),
            ))
    (ROOT / "image-mapping.json").write_text(json.dumps(mapping, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (ROOT / "image-checks.json").write_text(json.dumps(records, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Created {len(records)} PNG files; pixel boundaries, metadata and size checked.")


if __name__ == "__main__":
    main()
