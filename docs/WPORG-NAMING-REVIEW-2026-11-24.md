# Session Radar · WordPress.org naming review
Date: 2026-11-24
Status: Maintainer selected **Sessivue** / proposed slug **sessivue** for the private review branch; official WordPress.org reservation is still pending

## Source of the request
The Plugin Review Team's 2026-11-20 email about the already-submitted 1.0.0 ZIP flags the display name "Session Radar" and slug `session-radar` as possibly generic and confusing. The email explicitly identifies this as an AI-assisted finding, not a proven trademark infringement.

The review team requires the author to choose one of two approaches: establish the suitability/rights of the present name, or choose a distinctive display name and matching slug, update the metadata and translation references, upload one revised ZIP to the existing application, and request the new slug reservation in a reply to the SAME review email thread. Do not change the public slug without the maintainer's decision.

## Preliminary collision reconnaissance (public web searches, November 24, 2026)
- The exact phrase "Session Radar" is already used by a Visual Studio Marketplace extension, `non2xx.session-radar`:
  https://marketplace.visualstudio.com/items?itemName=non2xx.session-radar
- Another "Session Radar" plugin appears in the Orca community catalog:
  https://orca.dev/plugins/vribeirocmp/session-radar
- A Claude Code community plugin also uses `session-radar`:
  https://plugmyplugin.com/plugins/iatila-session-radar

Different tools/markets do not by themselves establish trademark infringement or rejection, but the exact phrase is demonstrably not unique. Search results are not a professional trademark-clearance opinion, do not establish plugin-slug availability, and do not reserve a WordPress.org slug.

## Selected identity and earlier exploratory alternatives
The author explicitly prefers that a newly named plugin NOT begin with "Amino". These are creative candidates to *research further*, not cleared or approved names:
- **Sessivue** · proposed slug `sessivue`: **maintainer-approved choice**. The name/slug must still be cleared by the WordPress.org Plugin Review Team.
- **Sessentra** · possible slug `sessentra`: session-centred coined term, but visually close to "Essentra"; this resemblance needs additional clearance.
- **Sesspectra** · possible slug `sesspectra`: coined "session" + "spectra"; easy to explain as a view of distinct WordPress login sessions, but requires the same clearance.

Choose only after a final search for software/plugin/product names, WordPress.org slug availability, relevant trademark databases, domain conflicts and SEO ambiguity. Do not make a definitive uniqueness or legal-safety claim based only on absence of search results.

## Review package and acceptance checklist
- [x] Public readme/FAQ uses Session Radar consistently and no longer calls the present plugin Orbit.
- [x] Public Privacy Policy Guide message and matching FR/ES PO sources refer to Session Radar.
- [x] GitHub README reflects the reviewer email, instead of saying no submission has happened.
- [x] Release preflight checks old public-brand regressions.
- [x] Maintainer chose Sessivue / proposed slug `sessivue` on November 24, 2026; WordPress.org approval remains outstanding.
- [ ] If changing slug: update main plugin filename/root folder/header, readme title, text domain, JS/CSS asset handles as appropriate, translations, source strings, install/migration guide, tests, docs and CI package checks; guard existing settings/privacy migrations.
- [ ] Resolve reviewer translation-pack recommendation while supporting the declared WP 6.4 minimum.
- [ ] Obtain green PHP, WordPress 6.4 and real WordPress/Chromium tests.
- [ ] Build a dedicated corrected 1.0.x submission ZIP; do not submit a 2.0 experimental branch.
- [ ] Maintainer uploads the tested ZIP to the *existing* application and replies concisely to the *existing review email thread*.
- [ ] Record reviewer approval separately from source/CI readiness.

## Isolation
Changes here are public-copy and test work only. No deployed plugin or production user data is modified. The 2.0 development PR stack remains independent. No WordPress.org upload or email reply has occurred.
