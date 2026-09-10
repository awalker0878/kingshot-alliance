# Acceptance matrix baseline review — 2026-09-10

The authorized full visual run at `70706069` passed 60 cases and failed the desktop/mobile acceptance matrix because `/alliance/command` does not exist. Officer overview and Officer briefs are rendered within the real dashboard. The corrected case still visits all seven original surfaces through actual authenticated navigation; no production routes were added to accommodate the test.

Review checkpoint: `75f4b709260c73ae6862b3708394ffb84f8eb611`. Hosted run `34475318799`, artifact `10151284039` (`acceptance-review`), captured rendered screenshots plus raw and normalized text for every surface in both viewports. Both cases reached their final comparison. All newly explicit semantic and date-validity assertions passed; the only failures were the obsolete expected hashes. Screenshots and complete captured text were inspected before recording these replacements. This review is not a passing full browser run; containing and repeated verification follows the update.

## Preserved contracts

| Surface | Reviewed fixture facts and presentation |
| --- | --- |
| Rally builder | The actual Capability Acceptance Bear Hunt management page, four readiness blockers, participation and rally management sections; all existing form and owner sections remain in the full text comparison. |
| Member profile | Acceptance Marshal; current scout state; 145,000,000 versus 120,000,000 power, +25,000,000, TC3 versus TC2, and the two distinct history entries. |
| Transfer campaign | Acceptance Candidate in screening, linked Governor, unassessed transfer eligibility, active Alliance arrival and the explicit absence of a Transfer participant. Existing candidate controls and empty states remain. |
| Intelligence timeline | Timeline Watch; latest 125000000 power/54 members versus 100000000/50, factual differences and retained history/provenance. No invented intent or quality ranking. |
| Alliance command | The actual dashboard Officer overview region: four items requiring attention, four Event blockers, two factual Intelligence changes and current Governor observations. |
| Officer briefs | The three actual dashboard brief groups, with 3/1/0 source facts and their needs-attention/not-available states. |
| Assistant | The actual authorized Assistant page, its discovery prompts and question form, including officer attention and stale/missing observation prompts. |

Fourteen candidate template references previously displayed missing localization keys. Commit `e21c4f41` connects them to existing catalogue entries without changing forms, actions, validation or authorization. The corrected labels, not unresolved keys, were reviewed in desktop and mobile screenshots. A separate owner-local source regression now checks those static references against the real fallback catalogue.

## Dynamic data is not behavioral data

The old normalizer replaced entire selected DOM leaves when they contained a year, yet missed dynamic dates in table cells, options and stat values. The replacement does not mutate the DOM. It normalizes only concrete UUID/ULID identities and validated formatted timestamps, preserving all surrounding labels, counts, power, progression, relative-age text, freshness and warnings. Calendar/time errors fail. Timestamp-bearing surfaces must still contain actual valid rendered dates. Ten executed unit cases protect these constraints; the candidate translation contract is an eleventh source case.

The full normalized text SHA-256 comparison remains, independently for each viewport and each of the seven surfaces. No numeric or textual tolerance was relaxed. Raw/normalized text stays attached for diagnosis. Per-surface screenshots are now captured only on a mismatching fingerprint, avoiding fourteen unnecessary screenshots in a successful matrix run. The existing twelve PNG baselines elsewhere were not changed.

The focused follow-up runs both projects twice with `--repeat-each=2 --retries=0`, with the same original visual fixture seeders. It also exercises Composer argument forwarding and unexpected-empty-selection failure. Its temporary workflow must be removed before final normal CI verification. Full PHP/frontend/browser/security/deployment gates remain mandatory, and PR #163 must not be merged by this work.
