# SessiVue 1.0.0 | WordPress.org correction gates

Status: **draft rebrand / CI pending** (November 24, 2026). This supersedes earlier distribution instructions in the historical Session Radar release-gate document.

## Proven submission history

- November 16, 2026: the 1.0.0 candidate was submitted on the same WordPress.org application under `session-radar` (confirmed by the maintainer's portal screenshot). Not approved.
- November 20, 2026: the Plugin Review Team requested corrections including public copy, name/slug, and WordPress.org-native translations.
- November 24, 2026: maintainer chose **SessiVue** and target slug `sessivue`. The new slug is not reserved yet. Do not claim approval.
- GitHub stacked draft PRs: #36 (editorial), #38 (native EN/FR/ES language packs), successor SessiVue rename PR. Never merge/release automatically.

## Engineering gates

- [ ] SessiVue renaming branch CI all green under modern WordPress, minimum WordPress 6.4/PHP 8.0, and supported PHP versions.
- [ ] WordPress Plugin Check reviewed with zero errors. Record each warning and rationale.
- [ ] Real multi-user session/role/nonce tests, Chromium keyboard/zoom/manual accessibility and privacy validation.
- [ ] SessiVue settings migration from both Session Radar and Amino Orbit, including uninstall isolation, opt-in preservation, and a rollback plan.
- [ ] Native `sessivue-fr_FR.mo` and `sessivue-es_ES.mo` packs tested in `wp-content/languages/plugins/`; English works without translations.
- [ ] ZIP `sessivue-1.0.0.zip` with `sessivue/sessivue.php`, no bundled PO/MO/POT, no test/development files, valid SHA-256.
- [ ] Test LAB only after explicit maintainer go-ahead and confirmed database backup.
- [ ] Request `sessivue` reservation from Plugin Review Team in the **same existing application/review email thread**, then submit precisely one reviewed ZIP if allowed.
- [ ] Record written WordPress.org acceptance and SVN access separately from GitHub checks.

## Privacy

Widget shows WordPress-authenticated sessions, not presence certainty. Only authenticated wp-admin activity signals, optional and OFF by default. IP display OFF by default. No anonymous-visitor tracking or external telemetry. Native WordPress sessions are never invalidated by disabling monitoring.

See [SessiVue migration notes](SESSIVUE-MIGRATION.md) for the exact install and old-option fallback sequence. Historical pre-submission data remain in the original commit history.

Official WordPress.org plugin guidance: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
