# SessiVue | Upgrade from Session Radar and Amino Orbit

**Name:** SessiVue. **Candidate slug:** `sessivue`. **Version:** 1.0.0 (review candidate). **GitHub repository:** `aminostudio/sessivue`.

## WordPress.org: existing application, not a new submission

The developer submitted the initial 1.0.0 application as `session-radar` on November 16, 2026. On November 20, the review team requested corrections. The approved new identity is **SessiVue**, but the directory slug is **not reserved or approved** yet. The maintainer must explicitly request `sessivue` in the existing review thread. Per the reviewers’ November 20 instructions, a corrected ZIP may be uploaded to the *existing* application before their slug reservation is confirmed. Do not open a second plugin application. GitHub CI is not WordPress.org approval.

## Manual migration on LAB or an installed site

1. Back up the WordPress database. Note the old settings and whether admin activity monitoring is enabled.
2. **Deactivate** the previous Session Radar or Amino Orbit plugin before activating SessiVue. Never run two editions together.
3. Install and activate the new candidate ZIP. The folder must be `sessivue/`, with `sessivue.php` as its entrypoint.
4. On its first activation, SessiVue copies settings from `session_radar_settings`, or from `amino_orbit_settings` if the former is missing. The settings land in **`sessivue_settings`**. Existing values, including an explicit monitoring opt-in, are retained. A previously missing privacy opt-in remains disabled.
5. Validate Dashboard widget, session list/filter/search, settings, browser refresh, role access, and FR/ES language packs. Native WordPress authentication sessions are not logged out. Short-lived historical activity observations intentionally start fresh.
6. After confirming the new settings are intact, delete the old **inactive** plugin if desired. Its uninstall routine can remove only old storage, not SessiVue's isolated data. Recheck settings.
7. To roll back, deactivate SessiVue before reactivating the older plugin and refer to the database backup.

## Storage ownership and privacy

- SessiVue: per-blog option `sessivue_settings`, usermeta `_sessivue_presence_site_<blog_id>` and per-blog `sessivue_daily_cleanup`.
- Pre-fix unscoped `_sessivue_presence` has no reliable blog attribution. The post-review update discards it **once network-wide** and records a network-level migration flag. A failed deletion is retried; no old timestamps are imported or shown. Site-specific disable, erasure and cron cleanup never delete it or another blog’s data.
- Legacy sources: `session_radar_settings` and `amino_orbit_settings` are read once on new activation when new settings do not exist. SessiVue does not delete legacy settings from Session Radar/Amino Orbit; it removes only its own unsafe unscoped observations during the one-time privacy repair.
- WordPress alone owns native authentication sessions. IP display and activity monitoring remain opt-in/off by default unless explicitly enabled in migrated settings. Activity is limited to authenticated wp-admin interaction and expires after 24 hours.
- Technical class `Amino_Orbit`, legacy AJAX action names and DOM/CSS selectors remain internal identifiers for the 1.0.0 review cycle. They are not public plugin branding; altering them requires independent compatibility QA.

## Release gate

Require successful minimum WordPress 6.4 and current WordPress CI, PHP 8.0+, security, i18n, privacy and manual browser tests. The reviewer ZIP must contain a single `sessivue/` root, no gettext PO/MO/POT files, and a matching `sessivue` text domain. No automatic merge into main, production change, LAB deployment, WordPress.org upload, or reviewer email. Confirm the added Multisite test and all prior checks before a corrected ZIP is uploaded.
