# SessiVue 1.0.0 | WordPress.org release gates

**Status as of 2026-11-25 (Europe/Paris): GitHub integration complete; WordPress.org review still pending.** The plugin is not approved or published in the WordPress.org directory.

## Submission and integration history

- 2026-11-16: initial WordPress.org application submitted as `session-radar`.
- 2026-11-20: Plugin Review Team requested editorial, identity and translation corrections.
- 2026-11-24: maintainer selected **SessiVue**; requested slug is `sessivue` (not confirmed or reserved).
- 2026-11-24: candidate PR #40 CI succeeded in all 5 jobs, including PHP 8.0/8.2/8.4, WordPress 6.4/PHP 8.0, real WordPress/Chromium QA and packaging.
- 2026-11-25 CET: #36 (editorial), #38 (WordPress.org-native translations) and #40 (SessiVue identity and settings migration) merged to `main`; alternative PRs #37 and #39 closed without deleting their branch history.
- 2026-11-25 CET: connected LAB verified to have `sessivue/sessivue.php` active at 1.0.0 on WordPress 7.1.5 / PHP 8.4.28. `sessivue_settings` exists; activity monitoring is OFF; `sessivue_daily_cleanup` is scheduled. This is a smoke check, not proof of a complete manual migration test.

## Completed checks

- [x] Candidate CI: all five jobs green, including minimum supported environment and browser/keyboard/privacy/scale checks.
- [x] Plugin Check 2.2.0: 0 errors; 3 recorded `slow_db_query_meta_key` warnings (bounded WordPress usermeta queries, not hidden or waived).
- [x] Verified private reviewer ZIP from CI run #164: `sessivue-1.0.0.zip` under a single `sessivue/` root with matching entrypoint and text domain, without PO/MO/POT files.
- [x] English source and WordPress.org-native FR/ES pack path tested in CI; translation source catalogs retained in Git.
- [x] GitHub review-correction PRs integrated into `main`.
- [x] Basic LAB plugin, settings and cleanup schedule confirmed read-only.

## Outstanding release gates

- [ ] Complete **manual** LAB check for at least two user roles, activity opt-in/revocation, settings persistence, browser refresh, and accessibility. Make a database backup before any destructive migration tests.
- [ ] If migrating an existing older install, validate import from `session_radar_settings` or `amino_orbit_settings`, the old-plugin deactivation/uninstall path and rollback. The LAB currently has neither legacy options nor an older active plugin; do not claim this was manually tested.
- [ ] In the **same existing WordPress.org reviewer email/application thread**, request confirmation that the candidate may be reviewed as **SessiVue** with slug `sessivue`. Do **not** start a second application.
- [ ] Upload the corrected ZIP only once the review team confirms the appropriate slug/process and the maintainer authorizes upload. Never claim that CI success grants approval.
- [ ] Record WordPress.org acceptance and SVN access independently.
- [ ] Optionally rename the GitHub repository from `aminostudio/session-radar` to `aminostudio/sessivue` in repository Settings (not the same as WordPress.org slug assignment). Update remote clones, release scripts and external integrations afterward.

## Distribution boundaries

Do not deploy to production or submit to WordPress.org automatically. No tracking of anonymous visitors; optional admin-activity monitoring is OFF by default. WordPress native sessions remain owned by WordPress. Store only short-lived plugin-only activity metadata when monitoring is enabled, and preserve explicit opt-in through legacy settings import.

See [SessiVue migration notes](SESSIVUE-MIGRATION.md) and [official WordPress.org plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).
