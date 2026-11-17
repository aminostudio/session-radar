# Session Radar 1.0.0 · WordPress.org release gates

**Status confirmed by the maintainer's WordPress.org plugin-submission screenshot, 2026-11-17:** Session Radar **1.0.0 was submitted on November 16, 2026** with assigned slug `session-radar`. The portal shows **Awaiting Review** (not approved). The submission history lists a current `session-radar-1.0.0.zip` after the older `amino-orbit-1.0.0.zip`. The portal displays 734 plugins awaiting first review as a point-in-time queue count; this is not a timeline or forecast. This status comes from the maintainer's authenticated portal screenshot, not from an independently fetched WordPress.org API. The GitHub source is public at `aminostudio/session-radar`.

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

## WordPress.org submitted review (not yet approved)

- [x] Submit Session Radar 1.0.0 to WordPress.org on November 16, 2026 (confirmed by the maintainer's submission-portal screenshot).
- [x] Obtain assigned plugin slug `session-radar` and upload the renamed `session-radar-1.0.0.zip`.
- [ ] Await human review; check email sent to the WordPress.org submission contact, including spam/junk.
- [ ] If reviewers request code or documentation changes, apply them in a focused PR, run full CI and Plugin Check, then submit a corrected ZIP **only when appropriate in the existing review thread**. Do not create another plugin application.
- [ ] Record formal approval and directory publication separately; the portal currently says **Awaiting Review**.

## Live-site and manual checks still outstanding

- [ ] Confirm the installed **Session Radar** 1.0.0 ZIP on #LAB, not just the former Amino ORBIT 1.0.0-rc.1. Verify any migration keeps settings unchanged and never overlaps two tracking plugins unexpectedly.
- [ ] Manually test current dashboard filters, search, status changes, sorting, refresh, compact display, and role-based permissions with distinct real logins.
- [ ] Test real browser zoom at 200% and 400%, keyboard-only navigation, and at least one screen reader (e.g. NVDA or VoiceOver).
- [ ] Verify site-owner notice/legal basis before enabling workplace activity monitoring; monitoring must remain OFF by default.
- [x] Confirm WordPress.org submission account and assigned directory slug `session-radar` in the maintainer's authenticated portal; `Contributors: aminostudiofr` remains the public readme entry.
- [ ] Confirm genuine release/support page and screenshots without exposing session names, IPs or other live users' data.
- [ ] Verify current WordPress core stable release before advertising `Tested up to`.
- [ ] Test bundled French/Spanish gettext vs. WordPress.org translation delivery on the final ZIP.
- [x] Submission authorization has been exercised by the maintainer, who submitted the plugin; **publication/review approval is still pending**. Do not push WordPress.org SVN or announce approval until the review team grants access.

## Privacy boundaries

The plugin is read-only over native WordPress sessions. Recent wp-admin activity monitoring is OFF by default; IP display is OFF by default; no additional user-history tracking or external telemetry is introduced for a release. Server permission and nonce validation remain mandatory for AJAX.

## Distribution

`session-radar.php` and `readme.txt` must both declare 1.0.0 in the final package. The ZIP root must be `session-radar/`, and runtime translation catalogs must be included. The renamed slug has a distinct upgrade path from `amino-orbit` (see [migration notes](SESSION-RADAR-MIGRATION.md)). GitHub CI is not a substitute for WordPress.org review. The current publication state is **submitted / awaiting review**, not unpublished and not approved.

Official guidance: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
