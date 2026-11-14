# Amino Orbit v0.3.3 · Real WordPress integration evidence

**Verified:** 2026-11-14  
**CI:** [GitHub Actions run #21](https://github.com/aminostudio/amino-orbit/actions/runs/43555917728)  
**Plugin source:** commit `3ac48967d155d65a79d0821e609d513bcbbf2daa`.  
**Scope:** disposable GitHub Actions runner only. Amino #LAB and all production servers were left unchanged.

## Real environment

- **WordPress Core 7.1.4**, downloaded and installed with WP-CLI 2.12.0.
- **PHP 8.2**, stock WordPress APIs, stock `WP_User_Meta_Session_Tokens` manager.
- **MySQL 8.0**, disposable GitHub Actions service database.
- Plugin installed and activated as `amino-orbit`.
- Real French `fr_FR` and Spanish `es_ES` WordPress language packs installed.
- Test fixture created a disposable administrator, an editor, and three valid authentication sessions.

## 24 integration assertions passed

- WordPress creates and persists two different native session token verifiers for the same user.
- The fixture deliberately makes two sessions share exactly the same login/expiry/browser/IP metadata.
- Session totals show **three native sessions from two distinct users**.
- Hidden IP addresses remain hidden by default.
- Distinct verifier-derived Orbit presence IDs ensure only **one of three sessions** is active, even with identical session metadata.
- An editor cannot see the widget by default, but can see it when explicitly allowed.
- An editor never sees WordPress-native IP addresses, even if an administrator opts in.
- An administrator can opt in to seeing native IP addresses.
- Destroying a WordPress session causes the widget to drop from three sessions to two.
- WordPress locale switching loads **real bundled French and Spanish gettext catalogs**, including the refresh label.
- The plugin registers authenticated AJAX handlers, not unauthenticated `wp_ajax_nopriv` routes.

Full details are in `tests/wordpress-integration.php`; the CI job executes it with **`wp eval-file` after real WordPress bootstrapping**.

## Bug discovered and fixed

The first integration run, [#20](https://github.com/aminostudio/amino-orbit/actions/runs/43555576329), exposed an error in v0.3.2: when two sessions were created at the same second from the same browser and IP, their metadata-only fingerprints were equal. This could incorrectly propagate a presence status between separate sessions.

WordPress's default `WP_Session_Tokens::get_all()` returns values only and discards each session's unique hashed token verifier. v0.3.3 therefore reads the built-in session metadata with its verifier keys and uses `HMAC-SHA256(verifier, WordPress auth salt)` as an opaque, per-session **Orbit presence key**. The token verifier is already a SHA-256 one-way digest in WordPress. Orbit neither persists the native verifier nor the raw login token.

Alternative `session_token_manager` providers with no stable verifier API continue to use the legacy metadata-based fingerprint as a fallback.

**Upgrade behavior:** sessions remain valid and authenticated. Existing v0.3.2 presence records may temporarily display *Session open* until the next authenticated admin activity signal, because the presence key format has changed. Old plugin metadata expires within 24 hours and is cleaned by the existing cleanup.

## Actual CI results

- PHP isolated smoke/cleanup/AJAX-security/gettext/JS syntax stages: **green on PHP 8.0, 8.2, 8.4**.
- Real WordPress integration job: **24/24 passed on PHP 8.2**.
- On the disposable 3-session fixture, the widget rendered in **1.87 ms with 2 WordPress DB queries**. This is **informational only**, not representative load testing, a stable benchmark, or evidence of performance at scale.

## Remaining release gates

- Real admin-browser AJAX pings, active/idle transitions, keyboard focus and visual accessibility.
- Security and accessibility checks in a browser and with a real WordPress non-admin HTTP session.
- Representative scale performance measurement.
- Rerun Plugin Check for v0.3.3 and document remaining static-analysis warnings.
- Review public distribution, changelog, and data-processing disclosures.

The repository remains private and **v0.3.3 remains a development build**, not a stable public release.

## WordPress Core references

- https://developer.wordpress.org/reference/classes/wp_session_tokens/get_all/
- https://developer.wordpress.org/reference/classes/wp_session_tokens/hash_token/
- https://developer.wordpress.org/reference/classes/wp_user_meta_session_tokens/get_sessions/
