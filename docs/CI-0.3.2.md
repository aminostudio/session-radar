# Amino Orbit 0.3.2 · GitHub Actions CI validation

Date: 2026-11-14. Scope: **repository source and isolated tests only**.

## Failure root cause and remediation

The initial GitHub Actions run at commit `fefa23f993eb545637d50c4630ca691bb5d2be2c` ([run #12](https://github.com/aminostudio/amino-orbit/actions/runs/43553427325)) failed only on PHP **8.0**, during `php tests/smoke.php`.

Actual error:

```text
PHP Fatal error: Uncaught ReflectionException:
Trying to invoke private method Amino_Orbit::fingerprint()
from scope ReflectionMethod in tests/smoke.php:69
```

This was **a test-harness portability problem, not a crash in the deployed plugin**. PHP 8.0 requires `ReflectionMethod::setAccessible(true)` to invoke a private method, while PHP 8.1+ implicitly allows it.

The fix adds `$method->setAccessible(true);` inside **`tests/smoke.php` only**. GitHub Actions `actions/checkout` was updated from v4 to v5 to remove a deprecated Node.js 20 action warning.

Commit: [`13e7905`](https://github.com/aminostudio/amino-orbit/commit/13e7905ee735fb6da81da1c4c2fe18e64e958976).

## Verified passing run

[**GitHub Actions run #13**](https://github.com/aminostudio/amino-orbit/actions/runs/43554813610) completed successfully.

| Test stage | PHP 8.0 | PHP 8.2 | PHP 8.4 |
| --- | --- | --- | --- |
| PHP syntax checks | Pass | Pass | Pass |
| PHP isolated smoke tests | Pass | Pass | Pass |
| Daily cleanup regression tests | Pass | Pass | Pass |
| Compiled translation catalog tests (FR/ES) | Pass | Pass | Pass |
| JavaScript syntax check | Pass | Pass | Pass |
| Overall job | **Pass** | **Pass** | **Pass** |

Results were confirmed through the **actual jobs and step conclusions**, not by inferring them from workflow source files.

## What this does and does not prove

The plugin's `Requires PHP: 8.0` requirement now has direct automated source-test coverage. All three supported matrix jobs passed.

This is **not** WordPress functional integration testing. The following release gates remain: real-browser admin UI locale checks, unprivileged/editor AJAX access checks, idle/logout behavior, visual accessibility checks, representative DB query benchmarks, and WordPress Plugin Check remaining warnings.

**No change to the PHP runtime plugin or installable ZIP** was required. The active #LAB version stays **0.3.2**.
