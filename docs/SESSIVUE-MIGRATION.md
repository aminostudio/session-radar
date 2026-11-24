# Sessivue migration guide (private review candidate)

**Status:** `sessivue` is a *proposed* WordPress.org slug. The existing WordPress.org application was filed under `session-radar`. The Plugin Review Team must confirm the change in the **same review thread** before a renamed ZIP is submitted. Do not use the new package on production until that confirmation and tests.

## Safe transition on a test WordPress installation

1. Back up the database and the current Session Radar plugin; record its options and privacy monitoring preference.
2. In **Plugins**, **deactivate** Session Radar (`session-radar/session-radar.php`). Do not delete/uninstall it yet. Sessivue refuses to activate if that predecessor is still active.
3. Install the tested `sessivue-1.0.0.zip`, containing exactly one plugin folder `sessivue/` with `sessivue.php`, and activate Sessivue.
4. On first activation, Sessivue **copies** the old `session_radar_settings` option into a new `sessivue_settings` option. If the older option is absent, it may import `amino_orbit_settings`. An absent/unset activity-monitoring opt-in is treated as **OFF**. Previously authorized monitoring remains enabled only when the old option had an explicit enabled value.
5. Verify the authorized dashboard widget, permissions/roles, refresh interval, activity setting, IP visibility and a manual refresh. Native WordPress login session tokens are **never moved or revoked**.
6. The old plugin's short-lived activity markers (`_session_radar_presence`) are **not copied**. They age out under the old plugin's retention policy or are removed by **uninstalling the old plugin after checking Sessivue**. Sessivue only uses its own `_sessivue_presence` metadata. Activity statuses may start fresh, which is intentional for privacy.
7. When the new plugin is confirmed functional, **delete the inactive Session Radar plugin from the Plugins page**. Its uninstall removes only its old `session_radar_settings`, `_session_radar_presence` and `session_radar_daily_cleanup`. Sessivue's new settings, records and cron are independent.
8. Retain a rollback archive and database backup. To roll back, deactivate Sessivue, restore Session Radar and restore the database backup only if appropriate. Never activate both simultaneously.

## Release invariants

| Public surface | Identity |
|---|---|
| WordPress plugin name | Sessivue |
| Main plugin file | `sessivue.php` |
| Plugin directory / proposed WordPress.org slug | `sessivue/`, `sessivue` |
| Text domain | `sessivue` |
| Translation sources in Git | `languages/sessivue-fr_FR.po`, `languages/sessivue-es_ES.po` |
| Distribution | `sessivue-1.0.0.zip`, no bundled PO/MO |
| New settings and activity metadata | `sessivue_settings`, `_sessivue_presence` |
| Old sources used only for migration | `session_radar_settings`, `amino_orbit_settings` |

The repository stays `aminostudio/session-radar` while review is pending; changing a GitHub repo name is separate from changing a WordPress.org plugin slug and can be handled after acceptance. This is a **private review branch**. Do not merge into `main`, deploy to #LAB or upload to WordPress.org without a targeted QA pass.
