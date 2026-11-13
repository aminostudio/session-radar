# Amino Orbit v0.3.0 · Integration and accessibility checklist

The isolated smoke tests do not replace actual WordPress/browser testing.

## Amino #LAB

1. Install the v0.3.0 ZIP by replacing the existing plugin.
2. Confirm settings persist: refresh **30 seconds**, inactivity **5 minutes**, admin-only access, IP addresses hidden.
3. Keep two sessions (normal and private Firefox) of the same account. Verify sessions 2, users 1, active 2 when both are interacting.
4. Leave one browser inactive for over 5 minutes. Verify its status becomes Inactive while the native session remains valid.
5. Explicitly log out of one browser. Verify its session disappears after refresh. Closing a window does not log out.
6. If an existing editor account is available, enable read-only visibility and then revoke it. Verify permissions and IP redaction.

## Language

- WordPress user profile language English (United States): plugin displays English.
- Français: bundled French translation loads.
- Español: bundled Spanish translation loads.
- Date formats and interpolated counts remain correct; WordPress timezone is Europe/Paris on #LAB.

## Keyboard, reader and layout

- Keyboard focus survives a timed refresh while focused inside the widget.
- Labels for session list and role fieldset are announced by a screen reader.
- Status labels remain readable without color cues.
- Validate at 320, 375 and 450 CSS pixels, text zoom to 200%, and forced-colors mode.
- Hidden browser tabs must not poll; returning to a visible tab should refresh.

## Privacy and performance

- No admin tracking JS on the public frontend and no third-party telemetry.
- Unauthorized AJAX refresh and invalid nonce are denied.
- User names are escaped; non-administrators cannot see IPs.
- Test admin-ajax request cost with concurrent sessions at the optional 30-second interval.
- Run WordPress Plugin Check and a real WCAG audit before the first public stable release.
