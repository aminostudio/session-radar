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

## Manual Plugin Check: Plugin Repo category (2026-11-14)

- Source: user-exported Plugin Check 2.2.0 JSON, generated `2026-11-14 02:29:04`, on Amino #LAB.
- Selection: **Plugin Repo** category only; **Errors** and **Warnings** included. The other categories were unchecked.
- Result: **0 errors, 1 warning** in `includes/class-amino-orbit.php`, line **38**.
- Warning code: `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`.
- Current implementation: `load_plugin_textdomain()` runs on the WordPress `init` action with the relative `/languages` path. The plugin is presently distributed privately via ZIP and bundles the `fr_FR` and `es_ES` PO/MO files.
- Disposition: **accepted/documented for development**, not ignored or marked resolved. WordPress Core guidance allows retaining this call when the plugin is **not hosted on WordPress.org**, provided it is deferred to `init`. Removing it merely to silence Plugin Check might break locally bundled translations without an alternative.
- Re-evaluate when preparing a WordPress.org directory release; verify real French/Spanish translations before changing translation loading.

Reference: https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/

## Pending: must not be represented as passed

- **Real WordPress administration UI localization** for `fr_FR` and `es_ES`. The authenticated WordPress user locale currently is `en_US`; no user language settings were changed for this audit.
- **Real editor-role / unauthorized AJAX** permission tests. LAB currently has only one existing administrator; no extra users were created.
- Explicit logout transition and five-minute idle transition in a real browser for v0.3.0.
- Full keyboard, screen-reader, zoom, and forced-colors accessibility checks.
- **Remaining Plugin Check categories:** General, Security, Performance, and Accessibility have not yet been run. The earlier `Plugin Repo` category result does not establish full-plugin compliance.
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

## Maintenance follow-up: v0.3.1

- Added a visible, translated partial-results notice when the scan exceeds 200 session-bearing users or reaches 500 valid sessions; the widget still shows up to 40 rows.
- Added isolated regression tests for the caps. Current WordPress settings and privacy defaults are unchanged.
- All **37** gettext source messages are included in both bundled French and Spanish locale catalogs.
- The private-bundle `load_plugin_textdomain()` Plugin Check warning remains documented, not silently suppressed.
- This is a development patch, not a claim of complete live Plugin Check validation.

## Plugin Check 0.3.1 and optimization in 0.3.2 (2026-11-14)

- User-provided JSON export: 0 errors, 3 warnings: bundled translation loader and two `meta_key` lookup heuristics.
- The cleanup write-amplification issue is addressed in v0.3.2; the indexed lookups are retained intentionally.
- Source report and disposition: [PLUGIN-CHECK-0.3.1.md](PLUGIN-CHECK-0.3.1.md).
- GitHub tests now include independent daily cleanup regression assertions.
- Warning counts may persist and are not represented as a clean or successful release gate.

## GitHub Actions CI verified (2026-11-14)

- Run #12 failed under PHP 8.0 because the smoke test invoked a private method through reflection without `setAccessible(true)`.
- The isolated test harness was corrected. **No installed plugin PHP was changed.**
- [Run #13](https://github.com/aminostudio/amino-orbit/actions/runs/43554813610) then passed the full PHP 8.0 / 8.2 / 8.4 job matrix, including syntax, smoke tests, daily cleanup regression, gettext catalogs and JS syntax.
- See [CI-0.3.2.md](CI-0.3.2.md). Real-browser access controls and performance measurements remain outstanding.

## AJAX security regression added (2026-11-14)

- `tests/ajax-security.php` exercises authenticated and anonymous permission gates, valid/invalid nonce handling, IP redaction, hashed session fingerprints, ping throttling and missing/expired native sessions.
- All three PHP matrix jobs in [run #16](https://github.com/aminostudio/amino-orbit/actions/runs/43555114386) passed, including the new AJAX security step.
- This is isolated testing only. Real WordPress role/AJAX integration still requires a separate account and browser session.

## Real WordPress integration regression (2026-11-14)

- Disposable GitHub Actions fixture now installs the latest WordPress Core and the plugin against temporary MySQL, creates an admin/editor plus independent native sessions, and installs French and Spanish language packs. **No accounts are added to Amino #LAB.**
- First real run confirmed 3 sessions and 2 users; it exposed a same-second fingerprint collision in v0.3.2.
- v0.3.3 derives per-session HMAC identifiers from WordPress's stored SHA-256 session-token verifiers. Raw login tokens are never stored, and custom session managers retain metadata fallback.
- Real integration CI must pass before the v0.3.3 archive is offered for installation.
