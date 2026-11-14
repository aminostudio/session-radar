# Amino Orbit 🪐

**A lightweight WordPress dashboard presence monitor, designed by Amino Studio.**

Amino Orbit is a GPL-2.0-or-later WordPress plugin that distinguishes **valid authentication sessions** from **recent activity in wp-admin**. It adds an elegant, responsive widget to the WordPress Dashboard with sessions, users, roles, login times, device labels, and configurable viewer permissions.

## Presence status

- 🟢 **Active:** an interaction was observed recently in the admin area.
- 🟠 **Inactive:** admin interaction was observed previously but is older than the configured threshold.
- ⚪ **Session open:** WordPress considers the authentication session valid, but no admin interaction was observed in the previous 24 hours.

A valid WordPress session is **not proof that someone is at their keyboard**. The plugin does not claim otherwise.

## Installation

1. Install the plugin ZIP via **Plugins → Add New → Upload Plugin**, or place the `amino-orbit` directory under `wp-content/plugins/`.
2. Activate **Amino Orbit**.
3. Visit **Dashboard → Home** to see the widget.
4. Configure viewer roles, status threshold and refresh interval in **Settings → Amino Orbit**.

**Requirements:** WordPress 6.4+, PHP 8.0+.

## Features

- Dashboard widget showing active, inactive and open sessions.
- Displays the account name, WordPress roles, login time and browser/OS labels.
- Activity beacons from authenticated admin pages, throttled to minimize requests.
- Refreshes the dashboard widget every 30, 60, 120 or 180 seconds (60 seconds by default).
- Configurable read-only visibility for non-administrator roles.
- IP addresses hidden by default, optionally visible to administrators only.
- Responsive, scoped CSS and dependency-free vanilla JavaScript.
- No telemetry, external APIs, or anonymous visitor tracking.

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
amino-orbit/
├── amino-orbit.php
├── includes/class-amino-orbit.php
├── assets/admin.css
├── assets/admin.js
├── uninstall.php
├── readme.txt
├── languages/ (gettext POT, French and Spanish PO/MO)
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

English is the source language. Bundled gettext catalogs provide French (`fr_FR`) and Spanish (`es_ES`) translations. The plugin follows the WordPress admin user locale; it does not force the site language. `.po` files can be edited by translators; `.mo` files are compiled for WordPress. A `.pot` template is included.

## Changelog

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
- [ ] Verify permissions with a separate editor account and actual logout/idle transitions.
- [x] Add isolated PHP tests, gettext catalog tests and JavaScript syntax checks.
- [ ] Complete real-browser integration tests and accessibility audit.
- [x] Translate user-facing strings (French, English, Spanish).
- [x] Run and triage the first full Plugin Check export (0 errors, 3 documented warnings).
- [ ] Benchmark query cost, complete security and accessibility review.
- [ ] Publish a stable v1.0 release after validation.

## License

**GPL-2.0-or-later**, © Amino Studio. See [LICENSE](LICENSE).

## Status

**v0.3.4 is a development build**, not a stable public release. Initial isolated tests and syntax checks are not substitutes for real WordPress integration testing.

## Quality assurance

- [v0.3.0 audit evidence](docs/QA-0.3.0.md) distinguishes checks performed from pending WordPress integration tests.
- [Pre-release QA checklist](https://github.com/aminostudio/amino-orbit/issues/1) tracks the remaining release gates.
- [GitHub Actions CI and AJAX authorization checks](docs/CI-0.3.2.md) confirm syntax, smoke, cleanup, gettext and security suites on PHP 8.0, 8.2 and 8.4.

- [Plugin Check 0.3.1 findings](docs/PLUGIN-CHECK-0.3.1.md) records all three warnings and their technical disposition.
- [Amino #LAB v0.3.2 performance baseline](docs/PERF-BASELINE-0.3.2.md) records aggregate query counts, observed timings, and remaining measurement gaps.
- [Real WordPress integration results for v0.3.3](docs/WORDPRESS-INTEGRATION-0.3.3.md) document 24 successful assertions, the fixed session collision and the remaining gates.
- [Real browser and accessibility QA for v0.3.4](docs/BROWSER-QA-0.3.4.md) documents 32 successful Chromium assertions and the corrected in-flight keyboard-focus race.
