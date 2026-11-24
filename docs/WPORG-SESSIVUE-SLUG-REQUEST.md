# WordPress.org: proposed slug change for the existing review

**Current (existing application):** Session Radar, `session-radar`, submitted November 16, 2026 by `aminostudiofr`.

**Requested name/slug:** **Sessivue**, `sessivue`.

**Status:** Maintainer approved this name for preparation on GitHub. Public searches did not reveal an obvious exact-name collision, but this is not an official slug reservation or trademark clearance. Ask the Plugin Review Team to check/confirm availability in the original review conversation.

## Draft 1: short slug reservation request (maintainer sends manually)

Subject: Re: [WordPress Plugin Directory] Review in Progress: Session Radar

Hello Plugin Review Team,

Thank you for the initial review of our pending plugin submission, currently named Session Radar (`session-radar`).

We would like to adopt a more distinctive public name, **Sessivue**, with the proposed plugin slug **`sessivue`**. Could you please confirm whether this slug can be reserved for our existing pending application under `aminostudiofr`?

We are preparing a revised ZIP that uses `Sessivue` consistently in the main plugin filename, header, text domain, readme, interface and translation identifiers. We have also updated our approach to use WordPress.org translation packs rather than bundled PO/MO files. We will upload the corrected archive to the existing review application and reply in this thread with the final confirmation, as directed.

Best regards,
Amino Studio

## Draft 2: after reviewer confirms the rename, CI passes and maintainer uploads the corrected ZIP

Subject: Re: [WordPress Plugin Directory] Review in Progress: Session Radar

Hello Plugin Review Team,

Thank you for your guidance. We have updated the existing submission to **Sessivue** (`sessivue`) and uploaded the corrected **1.0.0** ZIP.

The changes cover the public name and plugin slug, the readme/FAQ, source identifiers, WordPress-native translation loading, and the packaging of translations through WordPress.org language packs. Our private CI checks include WordPress 6.4 / PHP 8.0 minimum compatibility, WordPress session and permission tests, the installed FR/ES language-pack path, and Plugin Check. We have retained the old settings only as a controlled, safe upgrade source.

Please let us know if any additional changes are required.

Best regards,
Amino Studio

## Hard gates

- Do not send either draft automatically; the maintainer prefers to send mail personally.
- Do not modify or submit a second plugin application.
- Do not upload to WordPress.org, merge to `main`, or deploy to #LAB from this PR until QA and reviewer naming confirmation.
- Confirm all actual CI results and exact ZIP version before using Draft 2; do not imply that a passing CI proves directory approval.
