# Amino Orbit · Development builds

Installable WordPress plugin packages for **Amino #LAB**. Official source and tests are in [main](https://github.com/aminostudio/amino-orbit/tree/main).

## WordPress plugin ZIPs

- **[amino-orbit-0.3.2.zip](amino-orbit-0.3.2.zip)** · optimized daily cleanup: skips unchanged metadata writes and uses previous-value guards; all three Plugin Check warnings documented.
- [amino-orbit-0.3.1.zip](amino-orbit-0.3.1.zip) · bounded session scans and partial-results warning.
- [amino-orbit-0.3.0.zip](amino-orbit-0.3.0.zip) · French/Spanish and accessibility enhancements.
- [amino-orbit-0.2.1.zip](amino-orbit-0.2.1.zip) · optional 30-second refresh.
- [amino-orbit-0.2.0.zip](amino-orbit-0.2.0.zip) · dashboard refinements.

To install: WordPress **Plugins → Add New → Upload Plugin**, then replace the existing version. Each ZIP contains one top-level `amino-orbit/` directory.

**Development only.** The existing `load_plugin_textdomain()` and `meta_key` Plugin Check warnings remain under review, not suppressed. See [Plugin Check review](https://github.com/aminostudio/amino-orbit/blob/main/docs/PLUGIN-CHECK-0.3.1.md).
