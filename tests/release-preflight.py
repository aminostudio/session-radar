#!/usr/bin/env python3
"""Preflight the *private* Amino Orbit build before any WordPress.org submission.

Static checks cannot approve privacy/legal obligations or manual accessibility QA.
"""
from pathlib import Path
import re
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]


def header(content, key):
    match = re.search(r"^\s*\*\s*" + re.escape(key) + r":\s*(.*?)\s*$", content, re.M)
    if not match:
        raise ValueError(f"Missing plugin header: {key}")
    return match.group(1)


def require(condition, message):
    if not condition:
        raise ValueError(message)
    print("PASS:", message)


def check_sources():
    plugin = (ROOT / "amino-orbit.php").read_text(encoding="utf-8")
    readme = (ROOT / "readme.txt").read_text(encoding="utf-8")
    version = header(plugin, "Version")
    stable = re.search(r"^Stable tag:\s*(\S+)", readme, re.M)
    require(bool(re.fullmatch(r"\d+\.\d+\.\d+", version)), "Numeric semantic plugin version")
    require(stable is not None and stable.group(1) == version, "PHP header matches readme stable tag")
    require(f"AMINO_ORBIT_VERSION', '{version}'" in plugin, "Runtime constant matches plugin header")
    description = header(plugin, "Description")
    require(len(description) < 140, "Description fits WordPress.org plugin header guideline")
    require(header(plugin, "Text Domain") == "amino-orbit", "Plugin text domain matches slug")
    require(header(plugin, "Domain Path") == "/languages", "Bundled language path is declared")
    require(header(plugin, "License") == "GPL-2.0-or-later", "License header is GPL-compatible")
    require("License: GPL-2.0-or-later" in readme, "Readme license matches header")
    require((ROOT / "LICENSE").is_file(), "License file included")
    require("Contributors: aminostudio" in readme, "WordPress.org contributor name is documented for manual verification")
    require("== Privacy ==" in readme and "== Installation ==" in readme, "Readme documents privacy and installation")
    require("== Frequently Asked Questions ==" in readme, "Readme has a support-oriented FAQ")
    require("== Changelog ==" in readme and f"= {version} =" in readme,
            "Current version has a readme changelog entry")
    for relative in ("includes/class-amino-orbit.php", "uninstall.php", "assets/admin.js",
                     "assets/admin.css", "languages/amino-orbit-fr_FR.po",
                     "languages/amino-orbit-es_ES.po", "languages/amino-orbit-fr_FR.mo",
                     "languages/amino-orbit-es_ES.mo"):
        require((ROOT / relative).is_file(), f"Required plugin file: {relative}")
    require("activity_enabled' => 0" in (ROOT / "includes/class-amino-orbit.php").read_text(encoding="utf-8"),
            "Activity collection default is OFF")
    return version


def check_zip(path, version):
    require(path.is_file(), "Candidate ZIP exists")
    with zipfile.ZipFile(path) as bundle:
        require(bundle.testzip() is None, "ZIP checksum and CRC validation")
        names = bundle.namelist()
        require(bool(names), "ZIP has entries")
        require(all(name.startswith("amino-orbit/") and not name.startswith("/")
                    and ".." not in Path(name).parts for name in names),
                "Single safe amino-orbit top-level directory")
        required = {"amino-orbit/amino-orbit.php", "amino-orbit/readme.txt",
                    "amino-orbit/uninstall.php", "amino-orbit/LICENSE",
                    "amino-orbit/includes/class-amino-orbit.php",
                    "amino-orbit/assets/admin.js", "amino-orbit/assets/admin.css",
                    "amino-orbit/languages/amino-orbit-fr_FR.mo",
                    "amino-orbit/languages/amino-orbit-es_ES.mo"}
        require(required.issubset(set(names)), "Required runtime and translation files included")
        forbidden = ("tests/", "docs/", ".git/", ".github/", "__pycache__/", "node_modules/",
                     "scripts/", ".env", "wp-config.php")
        require(not any(any(fragment in name for fragment in forbidden) for name in names),
                "No development scripts, test fixtures or sensitive configuration in install ZIP")
        bundled = bundle.read("amino-orbit/amino-orbit.php").decode("utf-8")
        bundled_readme = bundle.read("amino-orbit/readme.txt").decode("utf-8")
        require(header(bundled, "Version") == version, "Packaged version equals source version")
        require(f"Stable tag: {version}" in bundled_readme, "Packaged readme stable tag matches version")


if __name__ == "__main__":
    try:
        current = check_sources()
        if len(sys.argv) > 1:
            check_zip(Path(sys.argv[1]), current)
        print("Amino Orbit private release preflight passed. Not a WordPress.org approval.")
    except (OSError, ValueError, zipfile.BadZipFile) as error:
        print("FAIL:", error, file=sys.stderr)
        sys.exit(1)
