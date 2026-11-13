# Amino Orbit manual integration QA

Development checklist for Amino #LAB. No production accounts should be created solely for testing.

## Two independent authenticated users

1. Keep an administrator logged into one browser (e.g. Firefox).
2. Log in with an existing editor account using another browser or browser profile.
3. Under Settings > Amino Orbit, explicitly allow the editor role.
4. Open Dashboard > Home in both profiles. Confirm each session is listed.
5. Interact with WordPress admin in each browser, then wait one widget refresh (60 seconds by default).
6. Leave one browser idle beyond the configured threshold while keeping the other active. Verify statuses diverge without claiming the inactive user has logged out.
7. Sign out of the editor profile. Verify the session disappears from the dashboard on refresh.
8. Open a second independent session for the same account. Verify session count may exceed unique user count.

## Access and privacy

- Disable editor visibility. The editor must lose access to both the widget and the data refresh endpoint.
- Enable display of WordPress-native IP addresses for administrators. The editor must never see those IPs.
- Verify that an unauthorized AJAX data refresh is rejected.
- Confirm that public-facing pages receive no tracking scripts.

## Usability

- Check widths of 320, 375 and 450 CSS pixels for layout overflow and text clipping.
- Verify keyboard focus for settings controls and the widget settings link.
- Verify automatic refresh is not presented as real-time user presence.
- Verify dates use the WordPress timezone (Europe/Paris on Amino #LAB).

This checklist requires real browser testing. The PHP smoke tests are isolated and cannot replace it.
