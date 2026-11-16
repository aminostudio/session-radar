# Session Radar 1.0.0 | LAB installer (no CI runner needed)

This isolated **release candidate** branch contains the actual WordPress-installable file at:
`dist/session-radar-1.0.0.zip`

The archive is assembled from the public-identity source commit `adaa3ff622fadaf71771ad1ef9b98895ea0d9e00` on PR #20. It has one top-level folder `session-radar/`, entrypoint `session-radar.php`, assets, core, readme, uninstall and FR/ES Gettext catalogs compiled from tracked PO sources.

The archive was constructed and its local ZIP directory/CRC fields validated without GitHub Actions because Actions is blocked for the private repository by the current GitHub account billing/quota state.

**Critical:** GitHub CI, live WordPress compatibility, official Plugin Check and a complete LAB migration test are still required before uploading this ZIP for WordPress.org review. Start with LAB only.

LAB sequence: backup database; deactivate (do NOT delete) Amino Orbit; install and activate Session Radar ZIP; verify settings copied from `amino_orbit_settings` into `session_radar_settings`, dashboard rendering and privacy opt-in; only then delete the inactive old plugin.

Use the direct binary file, not GitHub's 'Download ZIP' of the entire repository. Do not merge this build branch into main.
