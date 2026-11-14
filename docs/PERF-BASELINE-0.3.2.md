# Amino Orbit 0.3.2 · LAB performance and exposure baseline

**Date:** 2026-11-14  
**Environment:** Amino #LAB, WordPress 7.1.4, PHP 8.4.27, plugin 0.3.2 active.  
**Method:** read-only WPVibe WordPress CLI/SQL access, front-page HTML inspection, and source audit. No production sites or account settings modified.

## Database snapshot

All three SQL queries were SELECT-only aggregates, with WordPress prefix resolved by WPVibe.

| Query | Result | Tool-reported command time |
| --- | ---: | ---: |
| `SELECT COUNT(*) FROM {prefix}users` | 1 user | 4 ms |
| `SELECT COUNT(*) FROM {prefix}usermeta WHERE meta_key = 'session_tokens'` | 1 metadata row | 4 ms |
| `SELECT COUNT(*) FROM {prefix}usermeta WHERE meta_key = '_amino_orbit_presence'` | 1 metadata row | 11 ms |

Times are individual WPVibe command execution measurements. **They are not SQL engine profiling, not a sample distribution, and not a measurement of the complete `admin-ajax.php` widget refresh**. The current one-user site is too small to establish scaling behavior. Counts are metadata rows, not a count of simultaneously live WordPress sessions; one `session_tokens` row may contain more than one native session.

## Configuration

- Refresh interval: 30 seconds, inactivity threshold: 5 minutes.
- Additional viewer roles: none (administrators only).
- Native session IP display: disabled.
- The daily `amino_orbit_daily_cleanup` event remains scheduled.

## Exposure checks

- Public URL `https://labo.aminostudio.fr/` currently renders the **under-construction page**. Its HTML contained no `amino-orbit-admin` script, `assets/admin.js` or dashboard widget markup.
- Live `assets/admin.js` has a visibility guard and supports the configured 30-second refresh.
- Live PHP registers assets on `admin_enqueue_scripts`, not on public `wp_enqueue_scripts`; AJAX handlers use `wp_ajax_` rather than `wp_ajax_nopriv_`, and reference server-side nonce and permission checks. **Static evidence is not a full unauthorized-user integration test**.
- Neither the public page check nor the source inspection tests a normal frontend page while under-construction mode is enabled.

## CI status

The workflow source still contains PHP checks, isolated smoke tests, cleanup regression tests, translation checks, and `node --check`. GitHub's status lookup for the v0.3.2 commit returned no statuses. The available workflow-runs connector lookup is limited to PR runs and returned none; consequently **CI success remains unverified**, not failed.

## Remaining release gates

1. Measure real widget `admin-ajax.php` time, SQL queries and memory, ideally using Query Monitor, under several simultaneous authenticated sessions.
2. Test at least one larger, representative user/session dataset in a separate staging environment, without altering actual user accounts.
3. Verify French and Spanish translations in the actual WordPress administration.
4. Test authorized and unauthorized roles, explicit logout and idle status in a real browser.
5. Verify keyboard/screen-reader/high-contrast behavior and obtain an actionable accessibility audit.
6. Verify GitHub Actions run success.

**Conclusion:** Current #LAB footprint is tiny and the privacy boundary looks consistent in inspected code. This baseline is not evidence that the session lookup is fast at scale or that the plugin has completed its security/accessibility release gate.
