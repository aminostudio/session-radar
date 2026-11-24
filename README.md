# SessiVue 🪐

> **WordPress.org review:** The 1.0.0 candidate was submitted **November 16, 2026**, under its former name `session-radar`. The reviewer requested corrections **November 20, 2026**. **SessiVue** (`sessivue`) is the selected new identity, but its slug is not reserved, the corrected ZIP has not been uploaded, and the plugin is not yet approved. See [migration instructions](docs/SESSIVUE-MIGRATION.md).

**A lightweight WordPress dashboard presence monitor, designed by Amino Studio.**

SessiVue is a GPL-2.0-or-later WordPress plugin that distinguishes **valid authentication sessions** from **recent activity in wp-admin**. It adds an elegant, responsive widget to the WordPress Dashboard with sessions, users, roles, login times, device labels, and configurable viewer permissions.

## Presence status

- 🟢 **Active:** an interaction was observed recently in the admin area.
- 🟠 **Inactive:** admin interaction was observed previously but is older than the configured threshold.
- ⚪ **Session open:** WordPress considers the authentication session valid, but no admin interaction was observed in the previous 24 hours.

A valid WordPress session is **not proof that someone is at their keyboard**. The plugin does not claim otherwise.

## Installation

1. Install the plugin ZIP via **Plugins → Add New → Upload Plugin**, or place the `sessivue` directory under `wp-content/plugins/`.
2. Activate **SessiVue**.
3. Visit **Dashboard → Home** to see the widget.
4. Configure viewer roles, status threshold and refresh interval in **Settings → SessiVue**.

**Requirements:** WordPress 6.4+, PHP 8.0+.

## Features

- Dashboard widget showing active, inactive and open sessions.
- Displays the account name, WordPress roles, login time and browser/OS labels.
- Optional activity beacons from authenticated admin pages, disabled until an administrator explicitly opts in.
- Refreshes the dashboard widget every 30, 60, 120 or 180 seconds (60 seconds by default).
- Configurable read-only visibility for non-administrator roles.
- IP addresses hidden by default, optionally visible to administrators only.
- Responsive, scoped CSS and dependency-free vanilla JavaScript.
- No telemetry, external APIs, or anonymous visitor tracking.

## Site-wide activity opt-in

**Upgrading from pre-opt-in versions:** v0.3.8 removes existing legacy plugin activity records once when no explicit monitoring choice was ever saved, then records monitoring as OFF. Existing session visibility and dashboard settings are preserved.

**Recent activity signals are disabled by default** until a site administrator explicitly enables them in **Settings → SessiVue → Privacy**. The widget can still list native WordPress sessions without tracking activity. Disabling previously enabled monitoring immediately removes plugin-only activity metadata and prevents future server writes.

This is site-owner authorization, **not a declaration of consent or legal compliance on behalf of every user**. The site operator must inform affected team members and establish an appropriate legal basis where required. The WordPress Privacy Policy Guide receives editable suggested disclosure text.

[Implementation and release gates](docs/PRIVACY-OPT-IN-DESIGN.md). See also [native export and erasure in v0.3.7](docs/PRIVACY-TOOLS-0.3.7.md).

## Privacy and security

- Reads WordPress native `WP_Session_Tokens` data. It does **not** store raw authentication tokens.
- Last observed admin interaction is stored in short-lived user metadata keyed by an HMAC-derived session fingerprint.
- Presence records expire logically after 24 hours and are pruned via WP-Cron and subsequent interactions.
- Native WordPress session IP information is hidden unless an administrator opts in.
- Only administrators can modify settings; authorized roles can receive read-only widget access.
- Nonces and server-side capability checks protect authenticated AJAX routes.
- No frontend tracking or external data transfers.

> **Privacy notice:** This feature processes information about authenticated users' activity. Inform your team and review applicable workplace privacy and data-protection obligations.

## Repository layout

~~~text
sessivue/
├── sessivue.php
├── includes/class-amino-orbit.php
├── assets/admin.css
├── assets/admin.js
├── uninstall.php
├── readme.txt
├── languages/ (Git-only gettext template and FR/ES catalogs; excluded from WordPress.org ZIP)
├── tests/ (isolated smoke checks, gettext and manual QA)
├── README.md
└── LICENSE
~~~

## Development limitations

- Tracks activity inside `wp-admin`, not public-facing browsing.
- Scans up to 200 users with native session metadata and renders up to 40 session rows.
- Certain application-password or custom authentication methods do not expose a native browser session token.
- Alternative custom session managers without stable verifiers can still share a metadata-derived presence identity; default WordPress sessions cannot collide in that way.
- Background browser throttling can delay activity reports; this is not a WebSocket/live-presence system.
- Actual scheduled cleanup depends on WP-Cron execution.

## Internationalization

English (`en_US`) is the source language and needs no pack. French (`fr_FR`) and Spanish (`es_ES`) catalogs remain editable in Git for contribution to WordPress Polyglots. A WordPress.org submission ZIP omits `.po`/`.mo` files, and the plugin relies on WordPress core's language-pack loading rather than `load_plugin_textdomain()`. WordPress.org language packs are distributed only when the corresponding translations have been reviewed and published. Private CI exercises FR/ES through a simulated `wp-content/languages/plugins/` directory for both modern WordPress and the declared 6.4 minimum.

## Changelog

### 1.0.0 (stable candidate, awaiting final acceptance)
- Initial 1.0.0 build submitted for review on November 16, 2026; the WordPress.org Plugin Review Team requested corrections on November 20, 2026.
- Do not submit publicly until an explicit approval after all release gates pass.


### v0.9.0 (private development candidate)
- Reset all local session filters, show no-match feedback and offer compact view, with a11y checks.
- The official directory submission remains under review; GitHub releases are not an approval.

### v0.8.0 (private development)
- Restrict local role selection to already-rendered authorized sessions.

### v0.7.0 (private development)
- Add on-demand session refresh with localized accessible feedback and preserved focus.

### v0.6.0 (private development)
- Add deterministic session ordering, partial-results disclosure and an immediate-search bug fix.

### v0.5.0 (private #LAB build)
- Add local name/role/device search, combined with status filters. Installed on Amino #LAB.

### v0.4.0 (private development)
- Add local session status filters and accessible visible-count feedback.

### v0.3.9 (private release candidate)

- Correctly retries legacy presence deletion after a transient database failure, rather than marking an incomplete migration as done.
- Handles an empty legacy dataset without retry loops.
- Adds failure/recovery/empty-data regression coverage.
- This version remains private pending CI verification and deployment review.

### v0.3.8 (private development release)
- One-time privacy migration: an existing installation without the explicit monitoring preference purges old legacy plugin activity and saves `activity_enabled=0`.
- Keeps existing viewer roles, IP display choice, inactivity threshold and refresh interval; never deletes native WordPress authentication sessions.
- Additional isolated and genuine WordPress regression tests check first boot, idempotence and consent already enabled.


### v0.3.7 (private development)
- Adds WordPress core privacy exporter and eraser callbacks for plugin-specific, per-user activity metadata.
- Exports at most 32 valid interaction timestamps from the previous 24 hours, without revealing HMAC fingerprints, authentication tokens or IP addresses.
- Erases only the requested user's plugin activity data, preserving WordPress's native login sessions and unrelated user metadata.
- Adds PHP 8.0/8.2/8.4 and real WordPress integration regression tests; French and Spanish catalogs updated.


### v0.3.6 (private development)
- Explicit, default-off site-level opt-in for recent WordPress admin activity while keeping session visibility.
- Immediate purge of plugin-only presence data on deactivation of the setting; invalid/old AJAX pings cannot bypass the server gate.
- Suggested native WordPress privacy-policy disclosure and multilingual labels, with tested legacy MO compatibility.
- **Migration note:** monitoring will be OFF after upgrading from v0.3.5 until intentionally enabled by an administrator. Site-level opt-in does not itself establish consent by every monitored person.


### v0.3.5 (development)
- Fixes the hash-table offset stored in the bundled French and Spanish MO file headers. The old WordPress 6.4 POMO reader rejects a zero offset even for an empty hash table.
- Adds a binary-header regression to the gettext test suite and a real WordPress 6.4 + PHP 8.0 CI job.
- No change to plugin features, persisted settings, user tracking, or AJAX behavior.


### v0.3.4 (development)
- Preserves keyboard focus even when an AJAX refresh response arrives after focus has moved into the widget.
- Adds automated Chromium browser checks for activity transitions, role boundaries, focus preservation, mobile/high-contrast and axe-core WCAG 2.1 AA checks.


### v0.3.3 (development)
- Distinguishes native WordPress sessions even when browser/IP/login metadata are identical, by deriving a keyed HMAC from WordPress's hashed token verifier.
- Preserves the metadata-derived fallback for alternative session managers.
- Extends disposable WordPress CI to check native sessions, admin/editor visibility, logout and French/Spanish translations.
- Existing v0.3.2 activity indicators may show "Session open" until the browser sends another activity ping. This does not affect logins.


### v0.3.2 (development)
- Skips redundant user-meta writes during daily cleanup.
- Uses WordPress's prior-value guard on updates and deletions to avoid overwriting concurrent activity.
- Documents the three Plugin Check warnings, retaining the indexed session and cleanup lookups.


### v0.3.1 (development)
- Explicit notice when scanning 200 users or 500 sessions makes summary counts partial.
- Improved upper bounds on database and session work.


### v0.3.0 (development)
- Internationalized all admin labels and settings (English source, French and Spanish catalogs).
- Hardened role-setting sanitization against nested-array submissions.
- Added keyboard-focus protection during live widget refresh, high-contrast rules and semantic role-group markup.
- Optimized native session scans by skipping the unneeded total-user count.
- Kept the default 60-second refresh and the configured 30-second option.


### v0.2.1 (development)
- Optional 30-second dashboard refresh (60 seconds remains the default), without increasing the activity ping frequency.
- Dashboard polling is skipped when the browser tab is hidden; overlapping refreshes are prevented.

### v0.2.0 (development)
- Improved contrast, typography and small-screen layout.
- Changed “Live” to “Auto” to avoid implying real-time presence.
- Clarified login times and last activity descriptions.
- Fixed a request race for recent user interaction.
- Added multi-user and role smoke tests and a manual QA checklist.

## Roadmap

- [x] Verify two concurrent sessions of one account on Amino #LAB.
- [x] Verify real admin/editor permissions, inactivity and logout in Chromium on disposable WordPress.
- [x] Add isolated PHP tests, gettext catalog tests and JavaScript syntax checks.
- [x] Complete authenticated Chromium integration and automated scoped axe-core WCAG 2.1 AA checks (0 violations detected).
- [x] Translate user-facing strings (French, English, Spanish).
- [x] Run and triage the first full Plugin Check export (0 errors, 3 documented warnings).
- [x] Measure warm/cold query cost and limited eight-admin concurrent polling in disposable CI.
- [ ] Complete manual screen-reader and actual 200%/400% browser zoom checks.
- [ ] Complete WordPress.org-specific distribution review and prepare a public stable release after remaining human checks.

## License

**GPL-2.0-or-later**, © Amino Studio. See [LICENSE](LICENSE).

## Status

**Amino #LAB has been manually upgraded to v0.5.0 (WordPress reported a successful plugin update). Versions 0.6–0.9 remain GitHub development milestones and have not been installed on #LAB in this workflow.** Real WordPress, Chromium, automatic accessibility checks, three browser languages, official Plugin Check and a limited eight-administrator concurrent HTTP test now pass. Manual assistive-technology and actual browser zoom tests remain before public release.

## Quality assurance

- [v0.3.0 audit evidence](docs/QA-0.3.0.md) distinguishes checks performed from pending WordPress integration tests.
- [Pre-release QA checklist](https://github.com/aminostudio/session-radar/issues/1) tracks the remaining release gates.
- [GitHub Actions CI and AJAX authorization checks](docs/CI-0.3.2.md) confirm syntax, smoke, cleanup, gettext and security suites on PHP 8.0, 8.2 and 8.4.

- [Plugin Check 0.3.1 findings](docs/PLUGIN-CHECK-0.3.1.md) records all three warnings and their technical disposition.
- [Amino #LAB v0.3.2 performance baseline](docs/PERF-BASELINE-0.3.2.md) records aggregate query counts, observed timings, and remaining measurement gaps.
- [Real WordPress integration results for v0.3.3](docs/WORDPRESS-INTEGRATION-0.3.3.md) document 24 successful assertions, the fixed session collision and the remaining gates.
- [Real browser and accessibility QA for v0.3.4](docs/BROWSER-QA-0.3.4.md) documents 32 earlier Chromium assertions and the corrected in-flight keyboard-focus race.
- [v0.3.4 final automated QA baseline](docs/QA-AUTOMATED-0.3.4.md) records **36 browser checks**, 200%/400% simulated reflow, 205-user/510-session limits, warm/cold query measurements, and automated official Plugin Check (0 errors, 3 known warnings).
- [v0.3.4 eight-admin AJAX and real-browser locale tests](docs/QA-CONCURRENCY-AND-LOCALES-0.3.4.md) documents 16 successful concurrent HTTP calls and six FR/ES/EN UI assertions.
- [WordPress.org pre-submission assessment](docs/WORDPRESS-ORG-READINESS-0.3.4.md) reviews privacy/consent, translation delivery, declared minimum WP version and public identity requirements.
- [v0.3.5 legacy WordPress compatibility](docs/QA-0.3.5-COMPATIBILITY.md) verifies WordPress 6.4/PHP 8.0 FR/ES gettext loading and explains the fixed MO headers.
