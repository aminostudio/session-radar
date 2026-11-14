# Amino Orbit v0.3.1 · Plugin Check report (2026-11-14)

**Source:** the user's Plugin Check 2.2.0 JSON exported from Amino #LAB at `2026-11-14 03:04:41`. The JSON lists findings but does not itself record the selected check categories.

**Summary:** **0 errors**, **3 warnings**, all in `includes/class-amino-orbit.php`. This is not proof of a fully clean Plugin Check run.

| Source line in v0.3.1 | Code | Disposition |
| --- | --- | --- |
| 39 | `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` | **Accept for private ZIP build.** Bundled language files need explicit registration while not hosted on WordPress.org. The call already runs on `init`, per WordPress Core guidance. Revisit for a WordPress.org distribution. |
| 264 | `WordPress.DB.SlowDBQuery.slow_db_query_meta_key` | **Monitor.** A bounded `get_users()` query selects accounts with WordPress-native `session_tokens` metadata. It is intentionally limited to 201 users and 500 valid sessions, and a partial-results warning is shown. It cannot be removed without changing the source of session truth. |
| 459 | `WordPress.DB.SlowDBQuery.slow_db_query_meta_key` | **Optimized in v0.3.2, warning retained.** A daily, capped `get_users()` query locates Orbit's own presence metadata for cleanup. v0.3.2 stops rewriting unchanged metadata and uses previous-value guards for updates/deletions. The indexed lookup remains necessary. |

The database warnings are static heuristics. No execution plan, wall-clock query timing, or load test was included in the export. Do not claim either query is fast or slow without measurement.

## Verification still required

- Test real UI localization (French and Spanish).
- Run browser-based security/role and idle/logout tests on Amino #LAB.
- Benchmark 30-second refresh on larger test datasets with Query Monitor or equivalent.
- Test keyboard navigation, zoom and screen readers.
- Confirm actual GitHub Actions runs succeed.

## References

- WordPress Core translation guidance: https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
- WordPress `get_users()`: https://developer.wordpress.org/reference/functions/get_users/
- WordPress `WP_User_Query`: https://developer.wordpress.org/reference/classes/wp_user_query/
- WordPress `update_user_meta()`: https://developer.wordpress.org/reference/functions/update_user_meta/

**Publication gate:** Keep the plugin in development until the remaining real-world checks pass.
