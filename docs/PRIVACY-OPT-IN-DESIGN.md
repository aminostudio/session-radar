# Amino Orbit · Explicit activity opt-in (feature branch)

**Status:** private v0.3.6 release candidate, pending GitHub CI and review; not installed on #LAB.  
**Base:** stable private v0.3.5 on `main`, active on Amino #LAB.  
**Branch:** `feature/privacy-opt-in`. **Do not merge or deploy until CI and privacy review are complete.**

## Why this exists

The WordPress.org [plugin guidelines, section 7](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) discourage collecting user activity without an explicit opt-in. Amino Orbit uses only local WordPress data and no external services, but its activity timestamps are still information about authenticated people.

This development changes the **activity signal** from default-on to **site-administrator opt-in**. It does **not** hide existing native WordPress login sessions or claim that a site administrator's authorization automatically establishes an employee's freely given consent or every relevant legal basis.

## Behavior

| Condition | WordPress native sessions | Orbit presence beacons | Activity status |
| --- | --- | --- | --- |
| New install / no option saved | Visible to permitted viewers | Off | `Session open` |
| Administrator explicitly enables signals | Visible | On in authenticated wp-admin | `Active` / `Inactive` / `Session open` |
| Administrator disables signals | Visible | Stopped client-side and rejected by server | `Session open` |
| An older browser sends a valid ping after disabling | Visible | Server returns `tracked:false` without writing data | No new activity |
| A site upgrades from 0.3.5 without an explicit opt-in | Visible | Off until saved | Historic signals hidden; 24-hour cleanup still runs |

Disabling an explicitly enabled option calls WordPress's `update_option_amino_orbit_settings` hook and removes **only** `_amino_orbit_presence` user metadata globally. Native WordPress `session_tokens` data is never deleted by this operation. Activity timestamps are also hidden immediately when the option is off, even if older metadata still exists from a preceding installation.

The full dashboard widget and its polling remain available to administrators without monitoring enabled. When the visitor has no dashboard access and monitoring is off, the plugin doesn't enqueue its admin JavaScript. The client stops attaching activity listeners and scheduling pings. The PHP AJAX handler independently rejects new observations.

## UI and transparency

A new checkbox in **Settings → Amino Orbit → Privacy** reads `Enable admin activity signals`, followed by an explanation of the stored timestamps and the administrator's responsibility to inform affected people.

The plugin now uses `wp_add_privacy_policy_content()` on `admin_init` to offer two editable paragraphs in WordPress's native Privacy Policy Guide. The text discloses purpose, retention, native sessions and the absence of external transfers.

All five new strings are included in the English source and bundled FR/ES PO + legacy WordPress 6.4-compatible MO catalogs.

## What this does not yet solve

- Site-owner opt-in **is not an individual's affirmative consent**. Informing staff and ensuring a valid legal basis remain responsibilities of the site operator; applicability depends on context and local law.
- No new per-user preference/exporter/eraser is included in this PR; its scope is the mandatory site-wide tracking switch, immediate disable purge and editable policy text.
- A direct uninstall continues to remove Orbit metadata and settings as in v0.3.5.
- Real screen-reader/manual zoom checks and the final WordPress.org submission are separate release gates.

## Test gates

- [x] PHP 8.0/8.2/8.4: smoke, activity endpoint authorization and new privacy opt-in tests.
- [x] Real WordPress 6.4 + PHP 8.0: native session and fresh FR/ES gettext compatibility.
- [x] Real recent WordPress: administrator/editor sessions, actual login/logout, HTTP AJAX behavior, role restrictions.
- [x] Chromium: monitoring on/off, instant activity hiding, server rejection of authenticated ping after disabling, focus/zoom and axe-core.
- [x] Eight-admin concurrent refresh and 500-session/200-account caps.
- [x] Official Plugin Check 2.2.0: zero new errors; all warnings reviewed.
- [ ] Confirm wording, operator notice and legal basis for workplace usage before public WordPress.org release. Upgrading intentionally switches off prior activity monitoring by default.

No new version number or installable ZIP should be produced before completing this review.

## Automated QA evidence

[GitHub Actions run #60](https://github.com/aminostudio/amino-orbit/actions/runs/43607613608) passed the PHP matrix, WordPress 6.4 + PHP 8.0, recent WordPress/Chromium, eight-admin polling and official Plugin Check. The Chromium fixture confirmed that disabling monitoring removes stored activity, changes all activity states to open session, disables the browser's activity signals and returns `tracked:false` to valid authenticated AJAX requests. Only the prior three Plugin Check warnings remain; none is an error. Manual accessibility and staff-notice/legal-basis design questions remain.
