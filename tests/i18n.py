"""Check bundled gettext catalogs are complete and preserve printf placeholders."""
import gettext
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parents[1]
CODE = (ROOT / 'includes/class-amino-orbit.php').read_text(encoding='utf-8')
PATTERN = r"(?:__|esc_html_e|esc_attr__|esc_html__)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'amino-orbit'"
source = set(re.findall(PATTERN, CODE))
assert len(source) >= 36, f'Insufficient translated messages: {len(source)}'
for locale in ('fr_FR', 'es_ES'):
    with (ROOT / 'languages' / f'amino-orbit-{locale}.mo').open('rb') as mo:
        trans = gettext.GNUTranslations(mo)
    for msg in source:
        assert msg in trans._catalog, f'{locale}: missing {msg}'
        translated = trans.gettext(msg)
        assert re.findall(r'%[ds]', msg) == re.findall(r'%[ds]', translated), f'{locale}: broken placeholder in {msg}'
    print(f'PASS {locale}: {len(source)} source messages, placeholders and binary catalog verified')
print('All gettext checks passed.')
