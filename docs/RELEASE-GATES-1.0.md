# Amino ORBIT · v1.0.0 release gates (private)

**Status:** preparation only. #LAB currently has v0.9.0 installed and WordPress.org has not received a submission. The source, PHP header and stable tag remain 0.9.0 while these gates are evaluated. No automatic submission is configured.

## Automated gates
- [x] PHP 8.0, 8.2 and 8.4 lint and smoke suites in GitHub Actions from the 0.9.0 milestone.
- [x] WordPress 6.4/PHP 8.0 and recent WordPress/PHP 8.2 integration.
- [x] Real Chromium dashboard behavior, keyboard focus, axe-core, AJAX permissions, multiple administrators and scaling checks.
- [ ] New release preflight script passes on a fresh CI run, including the real private installable archive.
- [ ] WordPress Plugin Check both modes report no errors on final v1.0.0 candidate; inspect all warnings manually.
- [ ] FR/ES generated MO matches PO source strings on the final build.

## Manual release gates (not automatable or not yet independently verified)
- [ ] #LAB 0.9.0 functional review: filters, sorting, search, compact view, refresh feedback, role access, settings and actual session status.
- [ ] Real browser 200%/400% zoom and at least one named screen reader, including keyboard-only operation and accessibility review of dynamic announcements.
- [ ] Consent/notice approach reviewed for sites monitoring authenticated workplace users; operator decides who receives activity monitoring and documents its legal basis.
- [ ] Confirm a controlled WordPress.org account named `aminostudio`, the intended plugin slug and no name/trademark conflict.
- [ ] Prepare dedicated genuine plugin landing/support URL; `Plugin URI` presently uses the studio homepage.
- [ ] Confirm final screenshots, captions, support instructions and directory assets without exposing real users, IP addresses, or session metadata.
- [ ] Confirm actual current WordPress stable release before adjusting `Tested up to`.
- [ ] Review WordPress.org translation strategy (bundled FR/ES and the core translation directory), avoid silently removing a tested loader.
- [ ] Document release procedure for WordPress.org SVN / stable tag, and ensure future updates stay in sync.
- [ ] Final manual authorization before any public submission.

## Privacy invariants
The default activity setting remains OFF, browser tracking is opt-in, IP is hidden by default, WordPress-native login sessions remain untouched, and all widget AJAX access is checked server-side. No historical time series or external API is introduced solely for release readiness.

## Release mechanics
The GitHub Actions private ZIP contains only the runtime plugin and translations, not CI scripts or test fixtures. `tests/release-preflight.py` checks PHP/readme/runtime version consistency, the supported structure and the generated ZIP. An automated successful run does **not** constitute publication approval.

References: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ and https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
