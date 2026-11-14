# Amino Orbit 0.3.7 · WordPress personal-data privacy tools

**Branch:** `feature/privacy-export-erase` from the tested private 0.3.6 release. No deployment on Amino #LAB during CI.

## Purpose

WordPress includes native administrator workflows under **Tools → Export Personal Data** and **Tools → Erase Personal Data**. Version 0.3.7 registers Amino Orbit's own callbacks in these existing workflows, rather than introducing a separate privileged endpoint or sending personal information off-site.

## Export

- Looks up the WordPress account matching the request's **email address**.
- Reads **only** that account's `_amino_orbit_presence` user metadata.
- Returns at most 32 valid timestamps from the previous 24 hours in WordPress's standard export groups.
- Does not export HMAC-derived presence fingerprints, WordPress login tokens, session verifiers, IP addresses, or metadata of other users.
- Returns an empty result for an unknown email address, expired/no data, or extra pagination requests.
- Works even when current activity tracking is disabled, so residual older records can still be handled as personal data.

## Erasure

- Deletes the matching user's `_amino_orbit_presence` metadata **only**.
- Preserves WordPress's native `session_tokens`, login sessions and unrelated user metadata.
- Reports `items_removed`, `items_retained`, `messages` and `done` according to the Core eraser contract. A storage failure reports data retained rather than falsely claiming deletion.
- Repeated requests are safe and produce a successful no-op.

**Important:** Core WordPress remains responsible for verification and authorization of personal-data requests. The callbacks should be invoked by the native privacy workflow, not exposed as anonymous AJAX endpoints.

## Testing and boundaries

- Isolated PHP regression checks of registrations, cross-user isolation, timestamps, data minimization, failed deletion and idempotence.
- Genuine disposable WordPress integration exercises both registered privacy hooks and confirms a native authentication session survives erasure.
- The existing PHP matrix, WordPress 6.4 compatibility, Chromium interaction/privacy tests, concurrency and Plugin Check continue running.

This change does not grant a new legal basis to monitor staff and does not replace a site operator's information obligations. The monitoring opt-in introduced in 0.3.6 remains OFF by default.

**Release gate:** complete GitHub CI before merge and privately publishing the installable ZIP. No WordPress.org public submission is implied.
