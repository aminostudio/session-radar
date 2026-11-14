# Amino Orbit v0.3.4 · WordPress.org pre-submission review

**Reviewed:** 2026-11-14. This is a **readiness assessment, not a submission or an approval**. The repository remains private and #LAB is still using 0.3.4.

Primary WordPress.org sources:
- [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Common plugin review issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/)
- [Plugin headers](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/)
- [Readme format](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [Plugin privacy handbook](https://developer.wordpress.org/plugins/privacy/)
- [Plugin submission](https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/)

## Repository and package audit

| Area | Finding | Disposition |
| --- | --- | --- |
| Plugin header | Identifies name, version 0.3.4, text domain, domain path, PHP 8.0+, WP 6.4+, author, GPL-2.0-or-later. | Structurally present and consistent. |
| readme.txt | `Stable tag: 0.3.4` matches the PHP header; includes description, installation, privacy, limitations and changelog; five tags. | Good foundation. |
| License | GPL v2 text is bundled; declared `GPL-2.0-or-later` in plugin and readme. | WordPress.org-compatible; final license attribution review still needed. |
| Auth/session security | Real WordPress tests validate sessions, permissions, nonce checks, forced logout and IP masking. | All automated checks passed; not a manual penetration test. |
| Distribution | 13-file installable ZIP with a single `amino-orbit/` folder, built and tested privately. | Ready for internal installation. No public release or SVN deposit yet. |
| External scripts | Plugin assets are bundled locally, with no external service dependency or telemetry. | No third-party service API registration requirement observed. |
| Admin dashboard | Uses native `wp_add_dashboard_widget`, which WordPress normally exposes via Screen Options. | No custom dashboard takeover detected. |
| Data lifecycle | Stores an HMAC-derived key and short-lived activity timestamps; native session tokens remain managed by WordPress. Uninstall deletes plugin option and presence metadata. | Documented and scoped; see privacy review below. |
| Plugin Check | Official PCP 2.2.0, both static and runtime-assisted in CI #43: 0 errors, 3 previously reviewed warnings. | Compatible with current private development posture, not a substitute for human review. |

## Material questions to resolve *before* public WordPress.org submission

### A. Explicit consent and privacy disclosures: high priority

WordPress.org Guideline 7 prohibits tracking users without their consent; it specifically calls out automated user-data collection without explicit confirmation. The rule particularly addresses external-server contacts, but its stated tracking restrictions are broader.

**Current observed behavior:** installing and activating Orbit enables browser activity pings on authenticated `wp-admin` pages by default. No external service receives these signals. The dashboard stores recent timestamps per session, associates them with user accounts, and allows an administrator to see that activity.

**Risk:** the plugin is specifically designed to report authenticated people's activity. Local-only processing and 24-hour retention are good privacy controls, but may not fully resolve consent/notice expectations for monitoring employees or editors.

**Before submitting:** choose a clear, transparent site-owner activation/consent workflow, make the purpose and scope prominent inside Settings, explain who can see what, and supply a usable privacy-policy text for a site administrator. Consider whether `wp_add_privacy_policy_content()` and a WordPress personal-data exporter/eraser should be implemented for the plugin-specific `_amino_orbit_presence` field. Assess notice/consent with the operator for regulated workplace uses. This is a **review and design decision**, not a confirmed WordPress.org rejection.

Do not silently modify the current LAB installation or disable the core plugin feature while this is undecided.

### B. Public translation strategy: medium priority

The existing `load_plugin_textdomain()` at `init` is intentionally used by the private ZIP with bundled FR/ES catalogs; Plugin Check warns that it is discouraged for WordPress.org-hosted plugins, whose translations are normally loaded by WordPress. Decide whether to omit it in the first WordPress.org-targeted release and let the directory handle translation delivery. Confirm the FR/ES user experience with the actual public release packaging. **Do not strip it from the running private ZIP solely to suppress a static warning.**

### C. Public identity and presentation: medium priority

- `Plugin URI: https://aminostudio.fr/` points to the studio homepage, not an unmistakably unique plugin page. Prefer a genuine, dedicated official plugin page when one exists; do not invent the URL.
- `Contributors: aminostudio` must be checked against the actual, controlled WordPress.org account; the profile could not be verified automatically.
- Confirm the `amino-orbit` slug is available at submission and does not conflict with an existing WordPress.org listing or trademark. The public web search found no exact plugin listing, but this is **not** a reservation or a directory-side confirmation.
- Screenshots, support contact, accessible setup copy and a short FAQ are beneficial but not essential for the private ZIP.
- Maintain public source/build access if code is built or generated; the plugin's current assets and PHP source are human-readable.

### D. Compatibility baseline: verified for the declared minimum

**Resolved in private v0.3.5.** [GitHub Actions #48](https://github.com/aminostudio/amino-orbit/actions/runs/43563598946) passed real **WordPress 6.4 + PHP 8.0** integration for authentication sessions, role privacy, logout and AJAX hooks, plus fresh WordPress bootstrap gettext tests for **fr_FR and es_ES**. The genuine defect in the old MO hash-offset headers was corrected and permanently tested. See [compatibility evidence](QA-0.3.5-COMPATIBILITY.md). The latest WordPress 7.1.4 integration and Chromium suites also passed.

This confirms the advertised WordPress minimum for tested plugin features. It is not a comprehensive matrix of every intermediate WordPress version or third-party authentication/session manager.

### E. Public delivery: publication decision required

WordPress.org distributes releases from its SVN repository after review, not directly from the private GitHub `downloads` branch. Do not publish the private repository or submit a ZIP until explicit approval. Prepare matching PHP/readme version and tagged release when these points are resolved.

## Remaining independent accessibility review

The automated Chromium suite passed six FR/ES/EN UI assertions, 16 concurrent AJAX calls, scoped axe-core scans and CSS-reflow simulation. However, actual browser-menu zoom and at least one named screen reader, including axe-core's three manual-review items, have not been manually completed. This does not constitute a full WCAG certification.

## Recommended sequence

1. Make the privacy/consent design decision and document who may monitor whom.
2. Test against the advertised WordPress 6.4 minimum and decide public i18n behavior.
3. Add the final privacy/UI changes in **one release-candidate build**, not endless patch versions on #LAB.
4. Conduct the short human accessibility review. WordPress 6.4 minimum compatibility is now automated and passing.
5. Verify the real WordPress.org account, slug, dedicated page and publication readiness, then submit when authorized.

**Current decision: no public submission and no change to LAB 0.3.4.** This report identifies the remaining decisions without prematurely modifying a functioning development installation.
