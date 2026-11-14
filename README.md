# Amino Orbit · Private development ZIP builds

Installable WordPress plugin packages for **Amino #LAB**, built from [main](https://github.com/aminostudio/amino-orbit/tree/main). Packages remain private and do not constitute a WordPress.org release.

## Available versions

- **[amino-orbit-0.3.8.zip](amino-orbit-0.3.8.zip)** · one-time privacy migration purges legacy Orbit activity when a site upgraded from versions without explicit activity opt-in. Native WordPress login sessions and existing plugin settings remain intact. [All CI checks passed](https://github.com/aminostudio/amino-orbit/actions/runs/43613593641).
- **[amino-orbit-0.3.7.zip](amino-orbit-0.3.7.zip)** · adds native WordPress Export Personal Data / Erase Personal Data integration for plugin-only timestamps. Preserves WordPress login sessions, does not export HMAC identifiers/IP, and passes [full CI run #80](https://github.com/aminostudio/amino-orbit/actions/runs/43611190015).
- **[amino-orbit-0.3.6.zip](amino-orbit-0.3.6.zip)** · first privacy-focused build: recent admin activity signals are OFF by default until the site administrator explicitly opts in. Disabling removes Orbit's own presence records, while native login sessions remain visible. Includes WordPress Privacy Policy Guide suggestions and FR/ES strings. [All CI jobs passed](https://github.com/aminostudio/amino-orbit/actions/runs/43610102713).
- [amino-orbit-0.3.5.zip](amino-orbit-0.3.5.zip) · WordPress 6.4 French/Spanish gettext compatibility fix.
- [amino-orbit-0.3.4.zip](amino-orbit-0.3.4.zip) · keyboard focus-safe AJAX refresh.
- [amino-orbit-0.3.3.zip](amino-orbit-0.3.3.zip) · distinct native session identity.
- [amino-orbit-0.3.2.zip](amino-orbit-0.3.2.zip) · less redundant daily cleanup.
- [amino-orbit-0.3.1.zip](amino-orbit-0.3.1.zip) · bounded session scans.
- [amino-orbit-0.3.0.zip](amino-orbit-0.3.0.zip) · localized dashboard and accessibility.
- [amino-orbit-0.2.1.zip](amino-orbit-0.2.1.zip) · optional 30-second refresh.
- [amino-orbit-0.2.0.zip](amino-orbit-0.2.0.zip) · early dashboard refinements.

## Installation and upgrade notes

In WordPress: **Plugins → Add New → Upload Plugin**. Each ZIP contains one `amino-orbit/` directory.

**Important migration note:** upgrading from v0.3.5 to v0.3.6 disables recent activity signals until an administrator explicitly enables them under **Settings → Amino Orbit → Privacy**. The widget continues to list WordPress-authenticated sessions, but they appear as `Session open` without activity monitoring. No raw authentication tokens are stored by Orbit.

**Amino #LAB currently has v0.3.5 active**, unless deliberately upgraded by its owner. No automated CI test touches #LAB.

[Privacy design](https://github.com/aminostudio/amino-orbit/blob/main/docs/PRIVACY-OPT-IN-DESIGN.md) · [QA issue](https://github.com/aminostudio/amino-orbit/issues/1)
