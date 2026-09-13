# ADR-0080 — Unmatched Evidence preview and review handoff

Status: Accepted

## Context

The manager-only Debrief queue limited its Evidence records to 50 but loaded all extraction attempts and all fields for each latest attempt. Retained retries and malformed or long OCR fields could expand memory and the page. Its review links used an anchor against Screenshot Intake's newest 100 records, leaving older queued Evidence outside the destination page.

## Decision

Evidence continues to authorize the current Alliance occurrence and exclude saved reviews for the latest extraction before selecting the existing 50-Evidence queue. PostgreSQL selects one latest attempt per Evidence, ordered by creation time and identity. Fields are grouped by attempt/ordinal, with exact row counts and windowed selection of the first 25 preview rows per report. No extracted-field models or raw OCR text/boxes/warnings are hydrated for the preview. At most 50 attempt identities and 1,250 preview rows materialize.

Observed names are compact previews capped at 512 Unicode characters with an explicit ellipsis. Numeric previews accept canonical nonnegative integers within the database range; malformed or overflowing strings become unavailable instead of being clamped into invented numbers. Confidence retains the mean across the row's fields. Complete row counts remain separate from displayed rows and drive the Debrief's unmatched count. Larger reports display their visible/total Governor counts beside the owner review link.

The link explicitly selects its Evidence identity. Screenshot Intake rechecks current manager authority and selects that identity only within the authorized Alliance occurrence; a foreign occurrence is not found. This makes older reports addressable without loading a larger recent-record list. A link returns to the existing Screenshot Intake catalogue. No review, matching or result mutation moves into the Debrief.

## Verification and remaining scope

PostgreSQL regressions exercise 2,001 retained older attempts, 1,001 row ordinals with extra/long fields, exact counts, 25 previews, one hydrated attempt and zero field models; and an older report behind 102 newer records with scoped HTTP selection. Existing saved-review exclusion and cross-Alliance authority cases remain. A desktop/mobile journey opens a complete 31-row report from its 25-row preview after 102 newer screenshots.

HARD-139 separately tracks Screenshot Intake's own unpaged catalogue, nested classification/extraction/review/commit histories, Governor options, oversized extraction/review contract and score preview arithmetic. This decision bounds the Debrief preview and corrects its handoff; it does not claim the destination workspace is fully bounded.
