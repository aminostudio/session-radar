# Amino Orbit 0.3.4 · Real Chromium / WCAG browser QA

**Reviewed:** 2026-11-14. **Status:** full automated suite green; manual assistive technology and scale benchmarks pending.

## Test environment

- [GitHub Actions run #29](https://github.com/aminostudio/amino-orbit/actions/runs/43558989453) at commit `0c832a3932a4652e9e8f4f27393c8bd48ae82ece`.
- **Real WordPress 7.1.4**, temporary MySQL 8.0 service and PHP 8.2. The browser step runs the PHP built-in web server and real **Chromium** (Playwright).
- A disposable WordPress administrator and editor, with real authentication cookies, nonces and AJAX requests. All test users, session data, option changes and screenshots are created on the disposable CI runner, **never on Amino #LAB**.
- Automated accessibility checks: **axe-core**, with WCAG 2.0/2.1 A and AA tags on the widget and plugin settings panel.
- Tests committed in `tests/browser-e2e.cjs` and `tests/browser-fixture.php`, run by `.github/workflows/ci.yml`.

## Observed results

**32 browser assertions passed**. Selected checks:

- An anonymous HTTP POST to `admin-ajax.php` cannot obtain the session widget.
- The administrator logs into the genuine WordPress administration and sees the widget, including the configured 30-second refresh.
- The genuine browser sends its activity ping and WordPress changes the session presentation to **Active**.
- The widget refresh receives permission-checked AJAX HTML with HTTP 200.
- A ten-minute-old presence record appears **Inactive**; a subsequent real keyboard interaction and AJAX ping returns it to **Active**.
- Focus on the Settings link prevents an automatic replacement.
- **Race regression:** focus moved into the widget while an AJAX refresh was in flight (artificially delayed by 500 ms), and was preserved when the response arrived.
- Browser checks passed at a **375 px viewport** with simulated forced colors and reduced motion.
- An unauthorized, real logged-in editor receives **HTTP 403** for `amino_orbit_refresh`, even with a valid nonce; after an explicitly authorized role change in disposable CI, the editor sees the widget but not native session IPs.
- Logging out the real administrator prevents another authenticated refresh.
- No uncaught JavaScript errors were observed.

Automated accessibility result: **zero axe-core violations** in the Orbit dashboard panel and **zero violations** in the Orbit settings panel, within the WCAG 2.1 AA rules executed. The widget had **two** axe items requiring manual review, and the settings panel **one**. These are not demonstrated failures; they remain unverified manually.

## Accessibility defect actually exposed by CI

[Run #28](https://github.com/aminostudio/amino-orbit/actions/runs/43558812454) failed with:

```text
FAIL: AssertionError [ERR_ASSERTION]: Keyboard focus survives an in-flight AJAX response
```

In v0.3.3, `assets/admin.js` checked whether the widget contained keyboard focus **before initiating** `fetch()`, but did not repeat that check after the response was received. If the user focused a link while waiting for AJAX, DOM replacement detached the focused link.

v0.3.4 rechecks focus immediately before `current.replaceWith(next)`. When focus has moved into the panel during the request, no replacement is performed; the next eligible refresh will retry. The browser regression passes in [run #29](https://github.com/aminostudio/amino-orbit/actions/runs/43558989453).

## Additional CI stages

The GitHub Actions job matrix also passed all PHP 8.0, 8.2 and 8.4 isolated smoke, cleanup, AJAX security, gettext, PHP syntax and JavaScript syntax checks. The genuine WordPress integration job includes the previously verified 24 native-session, role, logout and locale assertions.

## What has NOT been verified

- Manual keyboard tab order with assistive technology, named screen readers and actual browser zoom to 200% or 400%.
- Full WCAG 2.2 AA compliance; axe-core checks only a subset of issues.
- Actual hidden-tab timing over hours and browsers beyond Chromium.
- Performance of the 30-second polling interval under hundreds of simultaneous authenticated administrators; the CI fixture is tiny.
- Production WordPress Plugin Check re-evaluation against v0.3.4; known warnings are documented separately.

The plugin repository remains **private**. No WordPress.org public release approval is claimed.

## Deployment

- [Installable development ZIP](https://github.com/aminostudio/amino-orbit/blob/downloads/amino-orbit-0.3.4.zip) has one top-level `amino-orbit/` directory.
- **Amino #LAB currently remains on v0.3.3** until the owner installs the ZIP. No server-side changes were performed during browser testing.
