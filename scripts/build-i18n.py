#!/usr/bin/env python3
"""Compile bundled PO files into WP 6.4-compatible GNU gettext MO catalogs.

The release workflow calls this before integration tests and ZIP assembly.
No third-party runtime dependencies.
"""
import ast
from pathlib import Path
import struct

ROOT = Path(__file__).resolve().parents[1]

def parse_po(path):
    entries = {}
    msgid = None
    msgstr = None
    field = None
    for raw in (path.read_text(encoding="utf-8").splitlines() + [""]):
        line = raw.strip()
        if not line:
            if msgid is not None and msgstr is not None:
                entries[msgid] = msgstr
            msgid = msgstr = field = None
        elif line.startswith("msgid "):
            msgid = ast.literal_eval(line[6:])
            field = "id"
        elif line.startswith("msgstr "):
            msgstr = ast.literal_eval(line[7:])
            field = "str"
        elif line.startswith('"'):
            value = ast.literal_eval(line)
            if field == "id":
                msgid += value
            elif field == "str":
                msgstr += value
        elif line.startswith("msgid_plural") or line.startswith("msgstr["):
            raise ValueError(f"Plural forms are not supported: {path}")
    return entries

def compile_mo(entries):
    pairs = sorted(((key.encode("utf-8"), value.encode("utf-8"))
                    for key, value in entries.items()), key=lambda pair: pair[0])
    count = len(pairs)
    originals = 28
    translations = originals + 8 * count
    hash_offset = translations + 8 * count
    data_offset = hash_offset
    source_table = []
    translation_table = []
    data = bytearray()
    for source, _ in pairs:
        source_table.extend((len(source), data_offset + len(data)))
        data.extend(source + b"\0")
    for _, translation in pairs:
        translation_table.extend((len(translation), data_offset + len(data)))
        data.extend(translation + b"\0")
    header = struct.pack("<7I", 0x950412DE, 0, count,
                         originals, translations, 0, hash_offset)
    return (header + struct.pack(f"<{2 * count}I", *source_table)
            + struct.pack(f"<{2 * count}I", *translation_table) + data)

if __name__ == "__main__":
    for locale in ("fr_FR", "es_ES"):
        po = ROOT / "languages" / f"amino-orbit-{locale}.po"
        mo = po.with_suffix(".mo")
        entries = parse_po(po)
        if not entries.get("") or "charset=UTF-8" not in entries[""]:
            raise ValueError(f"Invalid UTF-8 catalog header: {po}")
        mo.write_bytes(compile_mo(entries))
        print(f"{locale}: compiled {len(entries)} messages into {mo.name}")
