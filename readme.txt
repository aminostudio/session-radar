=== Sessivue ===
Contributors: aminostudiofr
Tags: sessions, dashboard, activity, logins, privacy
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See authenticated WordPress login sessions and recent admin activity in one lightweight dashboard widget. No visitor tracking.

== Description ==

Sessivue shows currently valid WordPress authentication sessions, their user and role, WordPress login time, browser/OS (when available), and recent interaction with wp-admin.

It distinguishes between:
* Active: a browser reported recent interaction with wp-admin.
* Inactive: previously observed activity, but not within the configured threshold.
* Session open: WordPress authentication is still valid, but no recent activity signal was collected.

"Session open" does not mean the user is online. Sessivue does not follow anonymous visitors or frontend behavior. It stores no extra IP address or raw authentication token. An administrator can optionally display the IP recorded by WordPress in each session.

The dashboard widget is visible to administrators by default. Under Settings > Sessivue, administrators can permit other roles to see it, adjust the inactivity threshold, change the refresh interval (30/60/120/180s), and show IP addresses to administrators only.

== Languages ==

English (en_US) is the source language and works without a translation pack. French (fr_FR) and Spanish (es_ES) translation catalogs are maintained in the public GitHub repository for contribution to translate.wordpress.org. Once the plugin is approved and its translations are validated by the WordPress translation community, WordPress automatically downloads language packs according to the user's selected admin language. The WordPress.org distribution ZIP does not ship PO/MO files or manually load the text domain.

== Installation ==

1. Upload the `sessivue` directory or its ZIP to `/wp-content/plugins/` using Plugins > Add New > Upload Plugin.
2. Activate Sessivue.
3. Visit Dashboard > Home, then Settings > Sessivue.

== Privacy ==

Sessivue reads the existing WordPress session manager. The only plugin-specific user metadata is an HMAC-derived session fingerprint with the last observed admin interaction timestamp; it never stores raw session tokens. Activity pings are batched and limited. Data is logically expired after 24 hours and is pruned during the user's next interaction and through a daily WP-Cron event. Actual cleanup time depends on WP-Cron execution. All plugin-specific data is removed on uninstall.

Activity signals are OFF by default until a site administrator explicitly enables them. Native WordPress authentication sessions remain visible to authorized viewers without activity tracking. When monitoring is enabled, only authenticated wp-admin sessions send updates. Disabling monitoring removes plugin-specific activity metadata and blocks further writes, without destroying WordPress authentication sessions. Site owners are responsible for informing affected users and determining an appropriate legal basis. WordPress's Privacy Policy Guide provides editable suggested disclosure text.

Nothing is sent to external providers. Viewer permissions are checked server-side on every data refresh.

Data retention of native WordPress session information is managed by WordPress, not Sessivue.

Use **Tools > Export Personal Data** or **Tools > Erase Personal Data** to process a confirmed personal-data request. Sessivue participates using the user's registered WordPress email address, exporting only recent activity timestamps or deleting only the person's Sessivue activity records. Native WordPress login sessions are never removed by Sessivue's privacy eraser.

== Frequently Asked Questions ==

= Does Sessivue show who is online right now? =

No. Sessivue displays WordPress authentication sessions. A valid login session does not prove someone is currently using the site. Optional admin activity signals only indicate recently detected interactions, not live presence.

= Does it monitor public visitors or send information elsewhere? =

No. There is no public-visitor tracking or third-party telemetry. When explicitly enabled by an administrator, only authenticated WordPress admin activity signals are stored locally as short-lived plugin user metadata.

= Is activity monitoring enabled automatically? =

No. It is disabled by default. An administrator must explicitly enable it in Settings > Sessivue. The site operator is responsible for informing affected users and establishing any necessary legal basis.

= Who can see session information? =

Administrators have access. An administrator may grant read-only widget access to additional roles. IP addresses from native WordPress sessions are hidden by default and can only be revealed to administrators.

= Can Sessivue log users out or revoke their sessions? =

No. The widget is read-only. Native WordPress authentication sessions and logout are managed by WordPress.

= What happens when I turn off monitoring or uninstall? =

Disabling activity monitoring deletes Sessivue-specific activity records while retaining native WordPress sessions. Uninstall removes Sessivue settings and plugin-specific metadata. WP-Cron cleanup may run later than its scheduled time.

= Why can the visible list be smaller than the session count? =

To keep dashboard queries bounded, the widget shows at most 40 session rows and scans a limited number of accounts and valid sessions. Filters and sorting apply only to the displayed subset. A partial-results warning appears when scan limits are reached.

= Which WordPress and PHP versions are supported? =

The minimums are WordPress 6.4 and PHP 8.0. English is available by default. French and Spanish translations are prepared for WordPress.org language-pack delivery once reviewed and approved.

== Limitations ==

* Presence monitoring covers `wp-admin` only, not the public frontend.
* Displays a maximum of 40 sessions, scans at most 200 matching accounts and 500 valid sessions. When a scan reaches these limits, the widget explicitly warns that results are partial.
* If a user logs in through an authentication method without a WordPress session token, no activity signal is recorded.
* Native WordPress session tokens are identified separately even when login time, expiry, IP and browser match. Alternative custom session managers without stable verifier identifiers may still share a metadata-based activity status.
* Activity pings are best-effort, not a live socket. Browser background throttling can delay updates.

== Changelog ==

= 1.0.0 =
* Prepared the renamed Sessivue candidate for the ongoing WordPress.org review of the former Session Radar submission; this is not an approval or a published directory entry.
* Adds a documented FAQ and strict integrity and version checks to the private build process.
* Carries forward all session controls, default-off activity monitoring and native WordPress privacy tools from 0.9.0.
* This changelog records the initial release candidate, not confirmation of WordPress.org directory approval.


= 0.9.0 =
* Adds a single keyboard-accessible action to reset text, role, status and sort filters without extra requests.
* Provides an explicit no-matches message, distinct from a site with no WordPress sessions.
* Offers a compact view without hiding session details, and retains it during AJAX updates.
* Improves control-to-list accessibility relationships while preserving user privacy defaults.


= 0.8.0 =
* Adds a localized role filter populated only by roles in the already-displayed authorized WordPress session rows.
* Combines role selection with client-side activity status, search, and sorting controls.
* Preserves a valid role selection across authorized AJAX refreshes, resetting gracefully if it disappears.
* No extra tracking, persistent preferences, additional requests or permissions.


= 0.7.0 =
* Adds an on-demand Refresh now button with accessible, localized progress, success, and failure status.
* Preserves keyboard focus when the user explicitly requests a refresh.
* Continues to defer automatic DOM replacement while focus is inside the dashboard widget.
* Uses only the existing authorized WordPress AJAX session endpoint, without extra tracking or storage.


= 0.6.0 =
* Adds accessible, client-side sorting of displayed sessions by original order, newest login and oldest login.
* Preserves the selected sorting method through AJAX refresh without storing the choice.
* Fixes search input binding so typing works before any status filter is clicked.
* Explains clearly when filtering and sorting cover only a capped subset of sessions.


= 0.5.0 =
* Adds local, live search by visible WordPress user name, role and device to the session widget.
* Combines search with the existing status filters and visible result count.
* Preserves the search query across dashboard refreshes without sending queries to the server or storing them.
* Excludes native session IP addresses and authentication tokens from search.


= 0.4.0 =
* Adds accessible status filters to the dashboard session list: all, active, inactive and session open.
* Filters only the authorized session rows already present in the widget; no additional collection, storage, or network requests.
* Remembers the selected filter during automatic widget refresh without interrupting keyboard focus.
* Reuses bundled English, French and Spanish status translations.


= 0.3.9 =
* Retries the legacy privacy migration if historic legacy activity records could not be deleted due to a database error.
* Treats an empty legacy activity dataset as a completed migration without unnecessary repeated operations.
* Adds regression tests for failed deletion, subsequent recovery, and empty legacy datasets.


= 0.3.8 =
* Performs a one-time privacy migration for installations upgraded from versions before the explicit activity opt-in.
* Removes previously saved legacy activity metadata when no explicit monitoring preference exists, while preserving WordPress login sessions and other site settings.
* Runs once per legacy installation and includes isolated PHP and real WordPress migration regression tests.


= 0.3.7 =
* Integrates WordPress's native Export Personal Data and Erase Personal Data tools for plugin-specific session activity.
* Exports only recent interaction timestamps, never session fingerprints, IP addresses or native authentication tokens.
* Erases only the requested WordPress account's plugin activity metadata, preserving native WordPress login sessions.
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
