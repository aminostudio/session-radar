# Amino Orbit 0.3.5 · WordPress 6.4 translation compatibility

**Verified:** 2026-11-14.  
**GitHub Actions:** [Run #48](https://github.com/aminostudio/amino-orbit/actions/runs/43563598946), commit `705f25a413a7f9ba72329041c04eb22725c63c30`.  
**Installation:** #LAB remains on **v0.3.4**. This report covers a private development ZIP and disposable GitHub Actions environments only.

## Initial failure

The declared minimum is **WordPress 6.4 / PHP 8.0**. A new disposable WordPress 6.4 CI job revealed that the plugin's bundled French and Spanish `.mo` files could not be loaded by WordPress 6.4's POMO gettext reader.

Native session and permission checks succeeded, but the translated string `Session open` remained in English, even when WordPress was booted directly in `fr_FR`. The failure was therefore real and not merely caused by a dynamic locale-switching test.

## Root cause and correction

The original GNU MO headers had a valid magic number and 38 entries, but `hash_offset=0` even though the translation index ended at byte **636**. WordPress 6.4's `MO::import_from_reader()` insists that the hash table address immediately follow the original and translation length tables, including when the hash table's actual entry count is zero. Newer WordPress gettext loaders had tolerated the malformed header.

v0.3.5 modifies only the **four-byte hash-table offset field** in each binary file:

| File | Original offset | Corrected offset |
| --- | ---: | ---: |
| `languages/amino-orbit-fr_FR.mo` | 0 | 636 |
| `languages/amino-orbit-es_ES.mo` | 0 | 636 |

Translations and string counts did not change. `tests/i18n.py` now checks the full GNU MO table offsets, so this regression blocks CI in future.

## Successful compatibility confirmation

Run #48 completed with **all five CI jobs green**.

- PHP smoke matrix: **PHP 8.0, 8.2, 8.4 pass**.
- Actual **WordPress 6.4 + PHP 8.0 + MySQL 8.0**: native session, permission, role, IP-redaction, logout and AJAX hook assertions pass.
- Real WordPress **6.4 fresh boot in `fr_FR`**: both PHP gettext lookup and translated dashboard widget pass.
- Real WordPress **6.4 fresh boot in `es_ES`**: both PHP gettext lookup and translated dashboard widget pass.
- Current WordPress 7.1.4 integration job, including Chromium roles/locale/accessibility checks, eight-administrator two-wave AJAX polling, bounded query benchmarks and official Plugin Check: **all pass**.

On WordPress 6.4, the translated catalog is tested with **a separate fresh WordPress bootstrap for each locale**, because dynamic locale-switching behavior differed from the newer runtime. This proves normal fresh-request translation loading, not same-process runtime language switching.

**Official Plugin Check (current WP CI):** 0 errors and 3 previously documented warnings in each of static and runtime-assisted invocations.

## Deliverable

The private [`amino-orbit-0.3.5.zip`](https://github.com/aminostudio/amino-orbit/blob/downloads/amino-orbit-0.3.5.zip) is preserved on the `downloads` branch, with one `amino-orbit/` top-level folder and 13 plugin files. Header and readme both declare 0.3.5.

Only the two MO binary headers, plugin version metadata, tests and development documentation changed. **No new user tracking, cookie, permissions or settings behavior** was added.

## Remaining public release gates

A manual named-screen-reader test, actual browser-menu zoom at 200%/400%, and review of three axe-core incomplete findings are pending. A separate public-distribution privacy/consent and WordPress.org presentation decision is needed. **No WordPress.org submission has been made.**
