# Amino Orbit · Development builds

This private branch contains installable ZIP packages for **Amino #LAB**. The source of truth and test suite live on the [main branch](https://github.com/aminostudio/amino-orbit/tree/main).

## WordPress packages

- **[amino-orbit-0.3.1.zip](amino-orbit-0.3.1.zip)** · 200-account / 500-session scan caps with visible partial-results warning; translations preserved.
- [amino-orbit-0.3.0.zip](amino-orbit-0.3.0.zip) · internationalization and accessibility refinements.
- [amino-orbit-0.2.1.zip](amino-orbit-0.2.1.zip) · optional 30-second refresh.
- [amino-orbit-0.2.0.zip](amino-orbit-0.2.0.zip) · visual refinements.

Each archive contains one top-level `amino-orbit/` folder. To replace an existing installation, go to WordPress **Plugins → Add New → Upload Plugin**.

**Development only.** These packages are not stable WordPress.org releases. The `load_plugin_textdomain()` warning for private bundled translations is documented in [QA](https://github.com/aminostudio/amino-orbit/blob/main/docs/QA-0.3.0.md).
