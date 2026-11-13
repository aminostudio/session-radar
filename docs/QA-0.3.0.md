# Amino Orbit 0.3.0: QA audit

**Status:** development build, not approved for public/stable release.  
**Reviewed:** 2026-11-14.  
**Source:** `main` at `0bf1b12c15e25cd346d97c12825fa8019c7cf4dc`.  
**Environment:** Amino #LAB, WordPress 7.1.4, PHP 8.4.27, WordPress user locale `en_US`.

## Automated/local verification: completed

- `php -l`: valid syntax for plugin entry point, main class, and uninstall script, using PHP 8.4 CLI.
- `node --check`: admin JavaScript syntax valid.
- Python `gettext.GNUTranslations` successfully parsed both bundled `.mo` files.
- **36 distinct source messages** are present in both `fr_FR` and `es_ES` catalogs.
- All `%s` and `%d` placeholder sequences are preserved across those catalogs.
- **26 additional local runtime assertions passed** in an isolated WordPress-API stub harness, covering:
  - English, French, and Spanish rendering of statuses and refresh labels.
  - Three native valid sessions, with one expired session omitted.
  - IP addresses hidden by default and invisible to non-admin viewers.
  - Explicit editor-role access, admin-only defaults, and malformed-role filtering.
  - Escaped display names, refresh interval selection, and translated session-list labels.
- Source identity confirmed with Git blob hashes: the tested entry point, main PHP class, CSS, JavaScript, readme.txt, and both compiled translation files match `main`.
- Sampled color pairs in the dashboard UI: minimum measured contrast **5.50:1** (body text; normal-text WCAG AA threshold is 4.5:1). This is not a complete color, CSS-state, or screen-reader audit.

## Amino #LAB: confirmed

- Plugin `amino-orbit` **active at v0.3.0**.
- Persistent settings unchanged: admin-only widget, IP hidden, 30-second refresh, five-minute inactivity threshold.
- `Europe/Paris` WordPress timezone.
- Daily `amino_orbit_daily_cleanup` WP-Cron event remains scheduled.
- Earlier hands-on verification with two Firefox profiles: two active sessions of one account counted as **2 sessions / 1 user**.

## Pending: must not be represented as passed

- **Real WordPress administration UI localization** for `fr_FR` and `es_ES`. The authenticated WordPress user locale currently is `en_US`; no user language settings were changed for this audit.
- **Real editor-role / unauthorized AJAX** permission tests. LAB currently has only one existing administrator; no extra users were created.
- Explicit logout transition and five-minute idle transition in a real browser for v0.3.0.
- Full keyboard, screen-reader, zoom, and forced-colors accessibility checks.
- End-to-end **Plugin Check (PCP) 2.2.0**: PCP is installed on LAB but the current connected automation API exposes no ability or REST route to run its checks. No claim of a PCP pass.
- CI run status for GitHub Actions on the private repository. Workflows are committed, but successful completion was not verified by this audit.
- 30-second polling server cost at scale; do not infer benchmarks from PHP syntax tests.

## Known limitations and future improvement

1. The session scan intentionally checks at most **200 accounts with session metadata**, and the dashboard lists at most **40 sessions**. The 40-session cap is disclosed in the UI, but reaching the 200-account query cap is not currently shown to users. Add an explicit partial-data warning or pagination before supporting large sites.
2. Very similar native sessions may share the same derived activity fingerprint (login second, expiry, IP, and user agent); documented limitation.
3. WP-Cron cleanup execution depends on site traffic or a real scheduled cron trigger; expiry is enforced in the display logic independently of physical cleanup.
4. Hidden browser tabs pause refreshes; an open authentication session must never be presented as proof of current physical presence.

## Release gate

Keep the repository **private** and classify v0.3.0 as **development** until the pending real WordPress checks, Plugin Check, and security/accessibility review are complete.

This report records checks actually performed and intentionally distinguishes source-level tests from verified WordPress integration behavior.
