# Session Radar 1.0.0 · WordPress.org release gates

**Audit refreshed 2026-11-17.** The repository was renamed from Amino ORBIT to **Session Radar**, is public at `aminostudio/session-radar`, and the current source declares **1.0.0**. Publishing source on GitHub or generating a ZIP is not WordPress.org acceptance. Verify directory status separately; do not infer approval.

## Automated checks completed on the PR #21 commit

- [x] PHP 8.0, 8.2, and 8.4 smoke and translation suites.
- [x] WordPress 6.4/PHP 8.0 minimum-compatibility tests.
- [x] Real WordPress/PHP 8.2 integration including Chromium behavior, keyboard/focus checks, axe-core, concurrent admins and bounded scan tests.
- [x] Plugin Check static and runtime: **zero errors, four warnings in each mode** at [workflow 43980645661](https://github.com/aminostudio/session-radar/actions/runs/43980645661).
- [x] Private ZIP preflight checks version consistency, runtime-only structure and ZIP integrity.

**Known Plugin Check warnings, not automatically cleared:**

1. `load_plugin_textdomain()` at line 44 is discouraged for plugins hosted on WordPress.org. Keep a tested bundled-FR/ES compatibility path until verified against actual translation delivery.
2. Three uses of `meta_key` in WordPress user queries (lines 68, 476 and 752) trigger slow-query warnings. These are bounded WordPress APIs, not unchecked SQL. Document or optimize only when it preserves correctness and privacy.

The latest successful PR CI is meaningful, but a red rerun screenshot can represent another attempt. Inspect the exact attempt and job log before concluding that every rerun succeeded.

## Live-site and manual checks still outstanding

- [ ] Confirm the installed **Session Radar** 1.0.0 ZIP on #LAB, not just the former Amino ORBIT 1.0.0-rc.1. Verify any migration keeps settings unchanged and never overlaps two tracking plugins unexpectedly.
- [ ] Manually test current dashboard filters, search, status changes, sorting, refresh, compact display, and role-based permissions with distinct real logins.
- [ ] Test real browser zoom at 200% and 400%, keyboard-only navigation, and at least one screen reader (e.g. NVDA or VoiceOver).
- [ ] Verify site-owner notice/legal basis before enabling workplace activity monitoring; monitoring must remain OFF by default.
- [ ] Confirm control of WordPress.org contributor account `aminostudiofr` and the requested directory slug `session-radar`.
- [ ] Confirm genuine release/support page and screenshots without exposing session names, IPs or other live users' data.
- [ ] Verify current WordPress core stable release before advertising `Tested up to`.
- [ ] Test bundled French/Spanish gettext vs. WordPress.org translation delivery on the final ZIP.
- [ ] Inspect directory submission/review status and obtain explicit authorization before publishing to WordPress.org SVN.

## Privacy boundaries

The plugin is read-only over native WordPress sessions. Recent wp-admin activity monitoring is OFF by default; IP display is OFF by default; no additional user-history tracking or external telemetry is introduced for a release. Server permission and nonce validation remain mandatory for AJAX.

## Distribution

`session-radar.php` and `readme.txt` must both declare 1.0.0 in the final package. The ZIP root must be `session-radar/`, and runtime translation catalogs must be included. The renamed slug has a distinct upgrade path from `amino-orbit` (see [migration notes](SESSION-RADAR-MIGRATION.md)). GitHub CI is not a substitute for WordPress.org review.

Official guidance: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
