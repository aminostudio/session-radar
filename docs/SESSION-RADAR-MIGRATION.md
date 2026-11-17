# Session Radar | migration from Amino Orbit

**Public product name:** Session Radar  
**Candidate WordPress.org slug:** `session-radar`  
**GitHub repository:** may be renamed from `aminostudio/amino-orbit` to `aminostudio/session-radar` after CI validation.

## WordPress.org (before initial review)

The **Amino Orbit** submission currently has slug `amino-orbit` and a submitted 1.0.0 ZIP. Do not delete that submission or start a second one.

1. Confirm this PR has all checks green and get the **installable** `session-radar-1.0.0.zip` from the GitHub Actions artifact (extract its outer ZIP first).
2. On the original WordPress.org submission page, use **change** next to the assigned slug to request `session-radar`. This change is typically available only before approval and may be a one-time change.
3. Once the accepted assigned slug is exactly `session-radar`, use **Upload updated plugin for review** to send the **inner installable** `session-radar-1.0.0.zip` on that same submission. Ensure both the submitted file and requested slug agree.
4. Verify the submission lists `session-radar` and file `session-radar-1.0.0.zip`; its status will still be Awaiting Review until the reviewers respond.
5. Only the owner should perform these WordPress.org account operations. If the slug is unavailable or the change control is locked, stop and do not upload a mismatched ZIP; reply to the review email or contact the Plugins Team.

## LAB (test migration without losing options)

1. Back up the LAB database and note all current Amino Orbit settings. Do not uninstall the old plugin yet.
2. Deactivate **Amino Orbit**. The old PHP class and hooks must never run simultaneously with the new plugin.
3. Install and activate `session-radar-1.0.0.zip`. Its activation hook **copies** `amino_orbit_settings` into `session_radar_settings` only if the new option is absent. No legacy data is deleted.
4. Verify Session Radar is the only active member of this pair; inspect Dashboard and Settings, refresh, session filtering and privacy opt-in.
5. Verify no PHP errors or unexpected browser console requests; ensure existing authenticated WordPress sessions were not logged out. Recent activity observations intentionally start fresh.
6. Only after all checks pass, **delete the inactive Amino Orbit plugin**. Its uninstall removes only the old option, metadata, and cron; new data is independent.
7. Recheck the Session Radar widget and settings after old plugin removal.

## Stable compatibility internals

The PHP class `Amino_Orbit`, selected AJAX action names, nonces, CSS/JS DOM selectors, and internal handles intentionally retain their original names to avoid a high-risk functional rewrite. They are **not** public plugin branding or directory metadata. The new WordPress plugin slug, text domain, display name, translated strings, and new settings storage use `session-radar`.

**Do not** submit or install the GitHub source repository ZIP: use only the plugin-only release ZIP from successful CI.
