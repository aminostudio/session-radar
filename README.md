# Amino Orbit · Development ZIP packages

Installable WordPress packages for **Amino #LAB**, built from our [main source branch](https://github.com/aminostudio/amino-orbit/tree/main).

## Available builds

- **[amino-orbit-0.3.3.zip](amino-orbit-0.3.3.zip)** · fixes identical-metadata session collisions. 24 real WordPress integration assertions passed, including permissions, logout and French/Spanish translations.
- [amino-orbit-0.3.2.zip](amino-orbit-0.3.2.zip) · safer daily activity cleanup.
- [amino-orbit-0.3.1.zip](amino-orbit-0.3.1.zip) · bounded session scans.
- [amino-orbit-0.3.0.zip](amino-orbit-0.3.0.zip) · localization and accessibility.
- [amino-orbit-0.2.1.zip](amino-orbit-0.2.1.zip) · optional 30-second refresh.
- [amino-orbit-0.2.0.zip](amino-orbit-0.2.0.zip) · early dashboard refinements.

Install via WordPress **Plugins → Add New → Upload Plugin**, then choose to replace the previous version. Each archive has one top-level `amino-orbit/` directory.

**v0.3.3 migration note:** Existing native WordPress sessions remain logged in. A session may temporarily display `Session open` instead of `Active` until its next back-office interaction, because Orbit now uses a distinct HMAC-derived session key.

[Real WordPress CI evidence](https://github.com/aminostudio/amino-orbit/blob/main/docs/WORDPRESS-INTEGRATION-0.3.3.md) | [QA gates](https://github.com/aminostudio/amino-orbit/issues/1)

All builds are **private development versions**, not stable WordPress.org releases.
