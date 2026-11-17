#!/usr/bin/env python3
"""Reproducible, plugin-only Session Radar build without GitHub Actions.

Usage: python3 scripts/build-release.py
Generates dist/session-radar-<version>.zip and dist/SHA256SUMS.txt.
Requires Python 3 only; all source files are read from this repository.
"""
from pathlib import Path
import hashlib
import re
import subprocess
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SLUG = "session-radar"
ENTRY = ROOT / f"{SLUG}.php"
DIST = ROOT / "dist"


def run(*argv):
    subprocess.run([sys.executable, *map(str, argv)], cwd=ROOT, check=True)


def main():
    match = re.search(r"^ \* Version:\s*([^\s]+)", ENTRY.read_text(encoding="utf-8"), re.M)
    if not match:
        raise SystemExit("Missing Plugin Version header")
    version = match.group(1)

    # Build MO catalogs from the tracked human-readable PO files.
    run("scripts/build-i18n.py")
    # Validate source metadata, package names, translations, and opt-in default.
    run("tests/release-preflight.py")

    package_files = [
        "session-radar.php", "readme.txt", "README.md", "uninstall.php", "LICENSE"
    ]
    for directory in ("assets", "includes", "languages"):
        package_files.extend(
            str(p.relative_to(ROOT)) for p in (ROOT / directory).rglob("*") if p.is_file()
        )
    package_files = sorted(set(package_files))
    DIST.mkdir(exist_ok=True)
    target = DIST / f"{SLUG}-{version}.zip"
    with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for relative in package_files:
            source = ROOT / relative
            if not source.is_file() or source.is_symlink():
                raise SystemExit(f"Invalid package file: {relative}")
            zf.write(source, arcname=f"{SLUG}/{relative}")
    run("tests/release-preflight.py", target)
    digest = hashlib.sha256(target.read_bytes()).hexdigest()
    (DIST / "SHA256SUMS.txt").write_text(
        f"{digest}  {target.name}\n", encoding="utf-8"
    )
    print(f"READY: {target} ({target.stat().st_size} bytes)")
    print(f"SHA256: {digest}")
    print("Candidate only: LAB and WordPress.org submission require owner review.")


if __name__ == "__main__":
    main()
