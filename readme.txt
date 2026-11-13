=== Amino Orbit ===
Contributors: aminostudio
Tags: dashboard, users, sessions, activity, privacy
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.3.0
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

Only authenticated wp-admin sessions send activity updates. Nothing is sent to external providers. Viewer permissions are checked server-side on every data refresh.

Data retention of native WordPress session information is managed by WordPress, not Amino Orbit.

== Limitations ==

* Presence monitoring covers `wp-admin` only, not the public frontend.
* Displays a maximum of 40 sessions from the first 200 matching accounts per scan, to keep database use bounded.
* If a user logs in through an authentication method without a WordPress session token, no activity signal is recorded.
* Multiple indistinguishable native sessions (same login second, expiry, IP and agent) may share one recent activity status. This does not affect authentication.
* Activity pings are best-effort, not a live socket. Browser background throttling can delay updates.

== Changelog ==

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
