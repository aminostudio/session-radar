# Amino Orbit 0.3.8 · One-time activity privacy migration

**Release candidate:** private GitHub branch `fix/privacy-upgrade-migration`, tested against an isolated WordPress environment. The public WordPress.org submission is a separate decision.

## Problem found during WordPress.org readiness review

The pre-opt-in plugin versions recorded `_amino_orbit_presence` while WordPress authenticated users visited the dashboard. From v0.3.6, `activity_enabled` defaults to OFF when its setting is missing, and the UI hides the former observations. However, when upgrading an existing site whose saved options did not yet include the checkbox, those older metadata entries were not immediately deleted. They would persist until the subsequent cleanup.

## Fix

At WordPress `init`, a one-time migration checks whether the saved `amino_orbit_settings` array **lacks** the `activity_enabled` key:

- Globally remove **only** `_amino_orbit_presence` metadata from WordPress users.
- Persist `activity_enabled=0` without replacing existing viewer permissions, IP setting or refresh/inactivity configuration.
- On later page loads, do nothing because the key now exists.
- When a site has already explicitly enabled activity reporting, do nothing.
- Never change native `session_tokens`, authentication cookies or login sessions.

The migration operates on the live database only after the upgraded plugin is activated. It is not run by CI on #LAB; CI tests use disposable WordPress and isolated test doubles.

## Test criteria

- PHP 8.0, 8.2 and 8.4 isolated tests for option preservation, data purge, idempotence and active consent.
- Real WordPress 6.4/PHP 8.0 compatibility and gettext tests.
- Real current WordPress session persistence, privacy callbacks and migration checks.
- Chromium focus, authorization, translations, privacy opt-in and axe-core; eight-admin concurrent AJAX refresh, bounded scans and official Plugin Check.

**No public release may be declared ready solely from automated checks.** Remaining review: real screen reader and browser zoom, site-operator notice and legal basis, WordPress.org author account and slug.
