# Amino Orbit · Private development ZIPs

Installable WordPress packages for **Amino #LAB**. Plugin source, CI tests and QA reports are on [main](https://github.com/aminostudio/amino-orbit/tree/main).

## Builds

- **[amino-orbit-0.3.4.zip](amino-orbit-0.3.4.zip)** · preserves keyboard focus when an AJAX refresh finishes after the user has focused the widget. Confirmed by [Chromium run #29](https://github.com/aminostudio/amino-orbit/actions/runs/43558989453) and the PHP 8.0/8.2/8.4 matrix.
- [amino-orbit-0.3.3.zip](amino-orbit-0.3.3.zip) · fixes identical-metadata session collisions.
- [amino-orbit-0.3.2.zip](amino-orbit-0.3.2.zip) · safer daily presence cleanup.
- [amino-orbit-0.3.1.zip](amino-orbit-0.3.1.zip) · bounded session scanning.
- [amino-orbit-0.3.0.zip](amino-orbit-0.3.0.zip) · localization and accessibility.
- [amino-orbit-0.2.1.zip](amino-orbit-0.2.1.zip) · optional 30-second refresh.
- [amino-orbit-0.2.0.zip](amino-orbit-0.2.0.zip) · dashboard refinements.

In WordPress: **Plugins → Add New → Upload Plugin**, then replace the installed version. Each ZIP contains one `amino-orbit/` directory.

**Development only:** private, not yet a stable public WordPress.org release. The existing Plugin Check warnings are documented. Manual assistive-technology and representative load reviews remain.
