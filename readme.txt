=== Amino Orbit ===
Contributors: aminostudio
Tags: dashboard, users, sessions, activity, privacy
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.3.7
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, privacy-conscious dashboard widget for WordPress authentication sessions and recent back-office activity.

== Description ==

Amino Orbit shows currently valid WordPress authentication sessions, their user and role, WordPress login time, browser/OS (when available), and recent interaction with wp-admin.

It distinguishes between:
* Active: a browser reported recent interaction with wp-admin.
* Inactive: previously observed activity, but not within the configured threshold.
* Session open: WordPress authentication is still valid, but no recent activity signal was collected.

"Session open" does not mean the user is online. Orbit does not follow anonymous visitors or frontend behavior. It stores no extra IP address or raw authentication token. An administrator can optionally display the IP recorded by WordPress in each session.

The dashboard widget is visible to administrators by default. Under Settings > Amino Orbit, administrators can permit other roles to see it, adjust the inactivity threshold, change the refresh interval (30/60/120/180s), and show IP addresses to administrators only.

== Languages ==

The plugin follows the WordPress user locale in wp-admin. English is the default source language; the bundled translations cover fr_FR and es_ES.

== Installation ==

1. Upload the `amino-orbit` directory or its ZIP to `/wp-content/plugins/` using Plugins > Add New > Upload Plugin.
2. Activate Amino Orbit.
3. Visit Dashboard > Home, then Settings > Amino Orbit.

== Privacy ==

Amino Orbit reads the existing WordPress session manager. The only plugin-specific user metadata is an HMAC-derived session fingerprint with the last observed admin interaction timestamp; it never stores raw session tokens. Activity pings are batched and limited. Data is logically expired after 24 hours and is pruned during the user's next interaction and through a daily WP-Cron event. Actual cleanup time depends on WP-Cron execution. All plugin-specific data is removed on uninstall.

Activity signals are OFF by default until a site administrator explicitly enables them. Native WordPress authentication sessions remain visible to authorized viewers without activity tracking. When monitoring is enabled, only authenticated wp-admin sessions send updates. Disabling monitoring removes plugin-specific activity metadata and blocks further writes, without destroying WordPress authentication sessions. Site owners are responsible for informing affected users and determining an appropriate legal basis. WordPress's Privacy Policy Guide provides editable suggested disclosure text.

Nothing is sent to external providers. Viewer permissions are checked server-side on every data refresh.

Data retention of native WordPress session information is managed by WordPress, not Amino Orbit.

Use **Tools > Export Personal Data** or **Tools > Erase Personal Data** to process a confirmed personal-data request. Amino Orbit participates using the user's registered WordPress email address, exporting only recent activity timestamps or deleting only the person's Orbit activity records. Native WordPress login sessions are never removed by Orbit's privacy eraser.

== Limitations ==

* Presence monitoring covers `wp-admin` only, not the public frontend.
* Displays a maximum of 40 sessions, scans at most 200 matching accounts and 500 valid sessions. When a scan reaches these limits, the widget explicitly warns that results are partial.
* If a user logs in through an authentication method without a WordPress session token, no activity signal is recorded.
* Native WordPress session tokens are identified separately even when login time, expiry, IP and browser match. Alternative custom session managers without stable verifier identifiers may still share a metadata-based activity status.
* Activity pings are best-effort, not a live socket. Browser background throttling can delay updates.

== Changelog ==

= 0.3.7 =
* Integrates WordPress's native Export Personal Data and Erase Personal Data tools for Orbit-specific session activity.
* Exports only recent interaction timestamps, never session fingerprints, IP addresses or native authentication tokens.
* Erases only the requested WordPress account's Orbit activity metadata, preserving native WordPress login sessions.
* Includes FR/ES translations and explicit PHP/WordPress integration tests for both privacy callbacks.


= 0.3.6 =
* Adds an administrator-controlled, default-off activity opt-in without hiding native WordPress login sessions.
* Stops browser activity signals and server-side activity writes when monitoring is disabled.
* Purges plugin-only presence data immediately when monitoring is turned off, without affecting login sessions.
* Provides editable text for WordPress's Privacy Policy Guide, translated into English, French and Spanish.
* Important when upgrading: activity reporting becomes disabled until an administrator enables it explicitly.


= 0.3.5 =
* Repairs bundled French and Spanish MO headers for compatibility with WordPress 6.4's original gettext/POMO reader.
* Adds regression checks for legacy MO table offsets and WordPress 6.4 with PHP 8.0.
* Does not change any tracking, settings, permissions, or presentation behavior.


= 0.3.4 =
* Preserves keyboard focus if a visitor enters the dashboard widget while an AJAX refresh request is in flight.
* Adds real Chromium and axe-core accessibility regression checks to GitHub Actions.


= 0.3.3 =
* Separately identifies native WordPress sessions by an HMAC of their stored one-way token verifiers, avoiding same-second browser/IP collisions.
* Retains a metadata-based fallback for custom session-token managers.
* Does not store raw token strings or add tracking data; existing activity markers may reset until the next interaction after upgrading.


= 0.3.2 =
* Avoids redundant user-meta writes during daily presence cleanup.
* Uses a previous-value check when pruning partially expired records to preserve newer concurrent activity.


= 0.3.1 =
* Discloses partial session statistics when the account or session scan limit is reached.
* Caps each dashboard scan to 500 valid sessions for predictable memory use.


= 0.3.0 =
* Adds bundled French and Spanish gettext translations; English is the source language.
* Improves keyboard focus, role checkbox semantics and localized accessibility labels.
* Ignores malformed role arrays in settings and skips an unnecessary WordPress user-count query.
* Preserves all v0.2.1 settings, including the optional 30-second widget refresh.


= 0.2.1 =
* Adds an optional 30-second dashboard refresh; retains 60 seconds as the default.
* Preserves the separate 60-second activity ping and skips hidden dashboard tabs.

= 0.2.0 =
* Improves dashboard readability and contrast, responsive behavior, and focus visibility.
* Clarifies automatic refresh status (not real-time presence), login date and last-seen activity.
* Keeps track of new interactions arriving while an earlier activity request is in flight.


= 0.1.0 =
* Initial development release: dashboard widget, role visibility settings, short-lived admin activity signals, privacy-safe reporting, and vanilla JS/CSS.
