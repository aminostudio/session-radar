"""Check bundled gettext catalogs are complete and preserve printf placeholders."""
import gettext
import pathlib
import re
import struct

ROOT = pathlib.Path(__file__).resolve().parents[1]
CODE = (ROOT / 'includes/class-sessivue.php').read_text(encoding='utf-8')
PATTERN = r"(?:__|esc_html_e|esc_attr__|esc_html__)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'sessivue'"
source = set(re.findall(PATTERN, CODE))
assert len(source) >= 36, f'Insufficient translated messages: {len(source)}'
for locale in ('fr_FR', 'es_ES'):
    with (ROOT / 'languages' / f'sessivue-{locale}.mo').open('rb') as mo:
        header = mo.read(28)
        assert len(header) == 28, f'{locale}: truncated binary catalog'
        magic, revision, count, originals, translations, hash_count, hash_offset = struct.unpack('<7I', header)
        assert magic == 0x950412de and revision == 0, f'{locale}: unsupported MO header'
        assert originals == 28 and translations == originals + 8 * count, f'{locale}: bad MO table positions'
        assert hash_offset == translations + 8 * count, (
            f'{locale}: malformed MO hash-table address (WordPress 6.4 POMO rejects this)'
        )
        mo.seek(0)
        trans = gettext.GNUTranslations(mo)
    for msg in source:
        assert msg in trans._catalog, f'{locale}: missing {msg}'
        translated = trans.gettext(msg)
        assert re.findall(r'%[ds]', msg) == re.findall(r'%[ds]', translated), f'{locale}: broken placeholder in {msg}'
    print(f'PASS {locale}: {len(source)} source messages, placeholders and binary catalog verified')
print('All gettext checks passed.')
