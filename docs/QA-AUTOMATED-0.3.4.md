# Amino Orbit 0.3.4 · Automated QA, performance and Plugin Check

**Date:** 2026-11-14. **Scope:** private plugin development branch.  
**Verified run:** [GitHub Actions #37](https://github.com/aminostudio/amino-orbit/actions/runs/43561123017).  
**Plugin:** v0.3.4, still installed on Amino #LAB; QA fixture and source tests only were changed in this audit.

## Verified test coverage

| Suite | Environment | Result |
| --- | --- | --- |
| PHP smoke, syntax, daily cleanup, AJAX security, gettext, JS syntax | PHP 8.0, 8.2 and 8.4 | All three jobs passed |
| Native WordPress integration | WordPress 7.1.4 / PHP 8.2 / MySQL 8.0 | 24 assertions passed |
| Real browser automation | Chromium + disposable WordPress | 36 assertions passed |
| axe-core WCAG 2.1 AA (scoped) | Dashboard widget and settings panel | 0 automatic violations in both scopes |
| Official Plugin Check 2.2.0, static | Disposable WordPress WP-CLI | **0 errors, 3 known warnings** |
| Official Plugin Check 2.2.0, runtime-assisted | Disposable WordPress WP-CLI with `--require=plugin-check/cli.php` | **0 errors, the same 3 warnings** |

The browser suite covers real authentication cookies, real AJAX HTTP 403/200, independently authorized editor/admin roles, IP redaction, login and logout, 5-minute inactivity threshold via a ten-minute-old presence fixture, resumed activity, keyboard-focus preservation including the in-flight AJAX response race, a narrow viewport, forced colors and reduced motion.

The additional CSS reflow probes pass at **simulated 200% and 400% zoom** for both the widget and settings page without detecting internal horizontal overflow. This is a CSS/browser automation approximation, **not a completed manual browser zoom test**.

axe-core additionally flagged **2 widget items and 1 settings item for manual inspection**. These are not confirmed violations, and no claim of complete WCAG compliance is made.

## Session and account limits verified

The tests run against a temporary WordPress database, using synthetic session records and disposable WordPress users; **Amino #LAB receives no test accounts or extra sessions**.

- **510 session records on one account**: scan stops at 500, 40 rows displayed, and a visible partial-results notice appears.
- **205 accounts with sessions**: 200 accounts counted, 40 rows displayed, and the partial-results notice appears.
- The native `usermeta` table was confirmed to have an index on `meta_key`.

## Measured rendering costs

Run #37 performed five sequential widget renders per condition on disposable CI hardware. Times are medians. **SQL queries here count WordPress queries during the widget render**, not the full request/bootstrapping cost.

| Synthetic scenario | Warm object cache | Cleared object cache each run | Median SQL queries with cleared cache |
| --- | ---: | ---: | ---: |
| 510 records, 500 scanned | 3.240 ms | 4.669 ms | 4 |
| 205 accounts, 200 scanned | 3.155 ms | 8.318 ms | 4 |

All limits held and all assertions succeeded. The warm-cache cases reported zero database queries because their user/session data were already cached; these are not cold SQL performance measurements.

**Limitations:** A series of five local requests does not measure concurrent authenticated sessions, internet latency, busy MySQL instances, object-cache variety, or total 30-second AJAX request costs. Do not extrapolate to 200 active administrators.

## Official WordPress Plugin Check automated

[Plugin Check 2.2.0](https://wordpress.org/plugins/plugin-check/) is now installed **only in the temporary CI WordPress instance** and executed through WP-CLI on each push. Both static and runtime-assisted checks ran and generated machine-readable findings; CI uploads them as the `amino-orbit-plugin-check-json` artifact.

Two distinct invocations report the same **three unique warnings** (not six):

1. `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` at line 39. Retained for the bundled translations in the private ZIP distribution; must be reconsidered for WordPress.org.
2. `WordPress.DB.SlowDBQuery.slow_db_query_meta_key` at line 285. The bounded native session-user lookup.
3. `WordPress.DB.SlowDBQuery.slow_db_query_meta_key` at line 508. The capped daily presence cleanup lookup.

The last two are static performance heuristics, not measured slow queries. The same code passed the account/session bounds and cache-cold latency checks described above. The GitHub Actions parser now correctly understands Plugin Check's file-grouped JSON output and **fails CI on any PCP ERROR** while preserving legitimate warnings for human review.

The first workflow attempt reported `no_plugin_readme` because the ephemeral CI installation had copied only code/assets and omitted the repository's `readme.txt`. The CI fixture now copies `readme.txt`, `README.md` and `LICENSE`. **No corresponding defect existed in the installable v0.3.4 ZIP.**

## Remaining checks before public release

- Manual real-browser 200% and 400% zoom, a named screen reader, and human review of the three axe-core incomplete items.
- Representative concurrent-load/30-second AJAX measurements on a suitably isolated environment.
- A final WordPress.org distribution review, notably removal or justification of the private-package translation loader warning.
- Actual release/tag and user-facing release notes when the publication decision is made.

**Decision:** Keep v0.3.4 private and operational on LAB, no unnecessary patch release or new installation. Automated test coverage is complete for the scenarios described; public-release clearance has **not** been claimed.
