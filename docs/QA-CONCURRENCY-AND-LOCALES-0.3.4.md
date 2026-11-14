# Amino Orbit v0.3.4 · Concurrent AJAX and browser locale validation

**Date:** 2026-11-14  
**Verified final run:** [GitHub Actions #43](https://github.com/aminostudio/amino-orbit/actions/runs/43562414820).  
**Environment:** disposable WordPress 7.1.4, PHP 8.2, MySQL 8.0 and Chromium. All files and accounts created in the temporary GitHub Actions runner; **Amino #LAB was not changed**.

## Concurrent authenticated refreshes

The test fixture `tests/concurrent-fixture.php` creates **eight different administrator accounts** inside a disposable CI database. `tests/concurrent-ajax.cjs` then authenticates each in a separate Chromium browser context, verifies **eight distinct WordPress auth cookies and nonces**, and sends `admin-ajax.php` refresh requests simultaneously from all eight contexts.

The HTTP server uses **`PHP_CLI_SERVER_WORKERS=8`** so the eight requests are not merely serialized by a single-threaded development server. The two coordinated bursts start **30,000 milliseconds apart**, matching the plugin's selected 30-second interval. These are deliberately triggered API-request waves, **not** evidence that eight real browser timers fired naturally at the exact same time.

| Metric | Wave 1 | Wave 2 (+30 s) |
| --- | ---: | ---: |
| Verified responses | 8/8 | 8/8 |
| Median per-request elapsed time | 90.026 ms | 72.866 ms |
| p95 (nearest rank over just eight requests) | 106.653 ms | 101.349 ms |
| Maximum | 106.653 ms | 101.349 ms |
| Total wall time for the wave | 108.377 ms | 102.124 ms |
| Aggregate widget HTML | 71,360 bytes | 71,440 bytes |

All 16 responses returned HTTP 200 with valid authorized session HTML. The test also checked that no `192.0.2.*` fixture IP appeared with IP display disabled. A first attempt exposed an issue **in the test fixture only**: a previous privacy test had left IP display enabled. The concurrent fixture now explicitly restores `show_ip=0` before its checks. No product-side correction was required.

**Interpretation:** This small test demonstrates that the plugin's current implementation handles a burst of eight independently authenticated administrators in this environment. It does **not** establish capacity for hundreds of simultaneous sessions, Internet latency, realistic PHP-FPM/Nginx settings or the traffic of a busy production website. The p95 is especially unstable with only eight observations per wave.

## Browser localization (genuine WordPress user locale)

`tests/browser-e2e.cjs` now changes the **disposable administrator's WordPress user locale** through the guarded CI fixture and loads the real authenticated Dashboard and Settings pages with Chromium.

| WordPress locale | Dashboard widget label checked | Settings panel heading checked |
| --- | --- | --- |
| `fr_FR` | `Actualisation : 30 s` | `Qui peut voir le widget ?` |
| `es_ES` | `Actualización: 30 s` | `¿Quién puede ver el widget?` |
| Default English | `Refresh: 30 s` | `Who can see the widget?` |

All **six real-browser locale assertions passed**. French and Spanish language packs were installed inside CI; no language was changed on #LAB.

## Overall #43 result

- PHP 8.0 / 8.2 / 8.4 isolated test matrix: **green**
- WordPress session/role/logout integration: **green**
- Chromium admin/editor permissions, idle/active transitions, focus-race regression, accessibility and language rendering: **green**
- 200-account / 500-session bounds and warm/cold query measurements: **green**
- Official Plugin Check 2.2.0, static and runtime-assisted: **0 errors, the same 3 previously reviewed warnings in each mode**
- Concurrent AJAX load: **16/16 successful responses**

## Outstanding human/publication checks

Manual screen-reader navigation and actual browser-menu zoom at 200%/400% remain outstanding. The automated reflow probes use simulated CSS zoom and are not a replacement for that review. A production-like load test should be performed if future deployment targets much larger editorial teams or sites. The three Plugin Check warnings still need review when preparing for WordPress.org submission.

**Release posture:** Keep v0.3.4 active on #LAB, and maintain a single private development ZIP. No new plugin release was necessary for these additional tests. Public-release approval is not yet claimed.
