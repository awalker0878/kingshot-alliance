# Screenshot Intake

Status: Implemented current state — 2026-08-22

Screenshot Intake turns user-provided KingShot screenshots into reviewed domain commands without transferring ownership of the resulting game data to the intake pipeline. The first supported evidence type is the **Bear Hunt battle report** because its value, validation boundary and destination result model are concrete.

This document is the product implementation contract and current-state reference. Every non-evidence-gated requirement below is implemented, tested, documented and reflected as current product truth.

## Product outcome

An authorized Player can start from a Bear Hunt Event occurrence, upload a battle-report screenshot, let the application classify and extract visible fields, review every extracted value and Player match, correct mistakes, detect duplicates, preview the resulting Bear Hunt changes, and commit the reviewed report exactly once. The original screenshot, machine attempts and human corrections retain immutable provenance. Deleting evidence never silently deletes committed Operations results.

## Ownership contract

`Intelligence/Evidence` owns:

- uploaded screenshot binaries and source metadata;
- immutable source checksums and derived-representation provenance;
- classification attempts;
- extraction attempts and extractor/model/ruleset versions;
- field-level raw text, normalized values, confidence and bounding regions;
- review revisions, manual corrections and Player-resolution decisions;
- exact, visual and semantic duplicate evidence decisions;
- commit attempts, idempotency keys and destination receipts;
- evidence lifecycle, redaction, deletion and retention state.

It does **not** own Event, EventOccurrence, Player, Alliance membership, Bear Hunt result, damage totals or rally metrics.

`Operations/Results` owns accepted Bear Hunt battle reports, report entries and all derived Event result state. Cross-context writes use scalar IDs/value objects through owner Actions. An `Intelligence/Evidence` application Action coordinates the commit handshake while owning no destination persistence.

The original evidence is never rewritten. If future extraction adds crop, rotation, resize, contrast adjustment or other preprocessing, each derived representation must carry its own checksum and source relationship. The first Bear Hunt implementation sends the retained original directly to OCR and therefore does not create a derived-image representation.

## Primary journey

1. Open a Bear Hunt occurrence and choose **Import battle report**.
2. Upload a supported private image.
3. The application security-scans, checksums and stores the evidence.
4. Exact duplicates are detected before expensive processing.
5. The application classifies the evidence.
6. A supported Bear Hunt report is extracted into field candidates.
7. The review surface displays the screenshot alongside extracted values, confidence and Player-match candidates.
8. The reviewer corrects or excludes fields/rows and resolves each committed row to a Player.
9. The application checks visual and semantic duplicate warnings.
10. A commit preview shows the concrete Bear Hunt result effect.
11. The reviewer commits the approved revision.
12. The Evidence commit Action sends a scalar Bear Hunt report command to `Operations/Results`.
13. Operations validates current authority/Event scope, persists the report idempotently, recomputes owned result aggregates and returns a scalar receipt.
14. Evidence records the receipt. Retrying after an interrupted acknowledgement returns the same result rather than double-counting damage.

## Lifecycle

Persisted Evidence lifecycle states are explicit. The normal progression is:

`uploaded → classifying → classified → extracting → needs_review → approved → committing → committed`

Exceptional persisted states are `unsupported`, `failed`, and `deleted`. Machine retries create new immutable attempts; they never overwrite previous attempts.

Rejected, unsafe, spoofed or oversized uploads fail before an Evidence row is created. An exact binary duplicate inside the authorized Alliance and Bear Hunt occurrence reuses the existing Evidence identity instead of creating a duplicate lifecycle row. Semantic duplicate blocking belongs to the immutable review state rather than the Evidence lifecycle. User deletion synchronously redacts retained source data and moves Evidence to `deleted`. Retention physically deletes expired uncommitted rows after redaction; committed Evidence retains a minimal provenance tombstone after its binary is removed rather than transitioning through artificial purge states.

## Bear Hunt extraction contract

The first extractor is intentionally narrow. It may commit only fields proven by fixture screenshots and visible game evidence. Initial review candidates are:

- visible report timestamp when available;
- ranking rows;
- reported rank;
- Player display name exactly as observed;
- damage value exactly as observed/normalized.

The extractor must not invent troop composition, heroes, buffs, rally role, `rallies_joined`, `rallies_led`, or other metrics when the screenshot does not expose them with a validated mapping.

Player names extracted from screenshots are observations, not identity. They cannot create or mutate Player records. Review resolves each accepted row to an existing Player through supported GameWorld/Alliance read APIs. Ambiguous or unresolved rows block commit unless explicitly excluded.

## Field confidence and review

Confidence is stored per extracted field, not only per screenshot. Each field retains:

- field key/ordinal;
- raw observed text;
- normalized candidate value;
- data type;
- confidence;
- optional bounding region;
- warnings/normalization notes;
- extraction-attempt identity.

A human correction creates review state and does not rewrite machine output or change historical confidence to `1.0`. Review revisions retain who changed what and when.

Every screenshot requires human review in the first release. Automatic commit is not part of this delivery contract.

## Duplicate contract

Duplicate handling has three distinct layers:

1. **Exact** — SHA-256 equality inside the authorized Alliance and Bear Hunt occurrence. Exact duplicates reuse the existing Evidence identity before extraction and never disclose cross-Alliance evidence.
2. **Visual** — perceptual similarity for resized or recompressed screenshots. This creates a review warning; it is not an automatic merge and the distinct binary remains its own Evidence record.
3. **Semantic** — a deterministic fingerprint after review using stable Bear Hunt report meaning such as occurrence, visible report time when available, resolved Player IDs, ranks and damage values. A semantic collision blocks duplicate commit until explicitly resolved by supported product behavior.

Duplicate checks must not disclose the existence of evidence from an unauthorized Alliance or tenant.

## Operations commit contract

`Operations/Results` receives a scalar command containing the actor Player ID, occurrence ID, source evidence/commit attempt IDs, visible report time when available, deterministic report fingerprint and reviewed entry values.

Operations must:

- reacquire current authority in its transaction;
- verify the occurrence belongs to a Bear Hunt Event with compatible scope/capability;
- validate every referenced Player against the Event target;
- reject/reuse an already accepted idempotency key/fingerprint;
- enforce idempotency and occurrence/fingerprint uniqueness at the database boundary as well as the application boundary;
- persist an immutable Bear Hunt battle-report ledger entry and its Player rows;
- capture any pre-existing Event Player result as a baseline before the first imported report for that Player;
- recompute the owned Bear Hunt result aggregate from the baseline plus currently accepted reports rather than incrementing blindly;
- restore the exact pre-import baseline when all imported reports for that Player are removed;
- populate only metrics supported by reviewed evidence;
- return a scalar receipt with accepted report identity and affected result values/counts.

Evidence cannot directly write `EventPlayerResult` or any Operations model.

## Commit recovery

Cross-context commit uses an explicit handshake:

`Evidence BeginCommitAttempt → Evidence CommitReviewedBearHuntEvidence → Operations RecordBearHuntBattleReport → Evidence MarkCommitSucceeded`

The destination idempotency key is stable for the immutable approved review meaning. If Operations commits and the caller crashes before Evidence records success, retry returns the existing destination receipt and does not duplicate damage. Failed Evidence acknowledgement attempts remain historical records; a subsequent Evidence attempt can recover the already-accepted Operations receipt using the same stable destination key.

## Deletion and retention

Evidence deletion and Operations correction are separate capabilities.

- Deleting evidence does not cascade into an accepted Bear Hunt result.
- Correcting/removing an accepted Bear Hunt report requires an audited `Operations/Results` owner Action and deterministic recomputation.
- Retention policy is configurable and enforced by a scheduled owner Action/command.
- A user-deleted uncommitted tombstone, failed/unsupported evidence, other inactive uncommitted evidence and committed binary each have independently configurable retention windows.
- Expired uncommitted evidence is redacted and then physically deleted.
- A committed record retains enough immutable metadata, reviewed meaning and destination receipt to explain which evidence/review/commit produced the Operations report after its binary and sensitive OCR/raw source text are redacted.
- Committed tombstones with no retained binary are excluded from bounded retention candidate scans so long-lived history cannot starve newer expired evidence.

Policy values live in `config/evidence.php` and `/docs/operations`; Vue/controllers do not own retention policy.

## Security and privacy

Uploads use private storage only. The pipeline validates allowlisted MIME types and size, verifies actual MIME and image dimensions, performs the shared repository upload security scan, computes SHA-256, generates non-user-controlled storage names, and removes stored bytes if persistence fails. Raw OCR/provider responses and diagnostics must not leak screenshot content, credentials, hashes, Player names, Alliance names, or cross-tenant duplicate information to unauthorized surfaces.

Authorization is checked before expensive work where useful and reacquired at each protected write boundary. Jobs and application Actions carry scalar IDs/value objects, never serialized Eloquent authority models.

## UX requirements

The first entry point is the Bear Hunt occurrence/results workflow, not a generic AI page. The UI provides:

- upload/progress/failure/retry states;
- screenshot preview with accessible alternative context;
- extracted-field table/form that remains usable without image-only interaction;
- confidence presentation that does not rely on color alone;
- Player matching and explicit unresolved/excluded states;
- manual correction with validation;
- exact/visual/semantic duplicate messaging in player-facing language;
- commit preview showing current value, imported contribution and resulting aggregate where applicable;
- accessible destructive confirmation for evidence deletion;
- keyboard operation and responsive layout;
- native Screenshot Intake message catalogues for every supported application locale rather than English-only fallback modules;
- stable action receipts for upload, review, commit, retry and deletion.

## Observability

Audit/outbox/diagnostic events cover material lifecycle transitions including upload accepted/rejected, classification/extraction attempted/failed, review approved, duplicate detected, commit started/succeeded/failed, destination deduplication, evidence deletion, retention redaction/purge and retention failure. Privacy-safe diagnostics report queue age, attempt latency, extraction failure rate, review correction rate, duplicate counts/rate, commit failure rate, retention failures, retained binaries and redacted evidence without exposing raw screenshot contents or identity/hash data.

## Delivery phases

A phase is `Complete` only when its behavior, authorization, persistence, UX, accessibility, localization, observability, tests and current-truth documentation are complete and applicable repository gates pass on the release candidate.

| Phase | Status | Outcome / exit condition |
| --- | --- | --- |
| 1 | Complete | Product contract and architecture ownership are documented and match implemented ownership. |
| 2 | Complete | Secure evidence upload, immutable source metadata, private storage, scan/checksum, exact duplicate boundary and lifecycle persistence are implemented and verified. |
| 3 | Complete | Versioned screenshot classification with immutable attempts, queued execution and unsupported/failure UX is implemented and verified. |
| 4 | Complete | The Bear Hunt battle-report extractor uses the fixture-proven narrow schema and deterministic normalization. |
| 5 | Complete | Immutable field-level confidence/provenance and extraction history are retained and verified. |
| 6 | Complete | Review revisions, Player resolution, manual correction/exclusion and commit eligibility rules are implemented and verified. |
| 7 | Complete | Exact, visual and semantic duplicate detection is implemented with tenant-safe disclosure behavior. |
| 8 | Complete | Commit preview and authoritative validation of reviewed meaning are implemented and verified. |
| 9 | Complete | Evidence sends scalar/value-object reviewed meaning into the `Operations/Results` owner Action with no foreign persistence writes. |
| 10 | Complete | Operations owns the Bear Hunt report ledger, entries, database uniqueness, baseline preservation, deterministic recomputation and idempotent aggregation. |
| 11 | Complete | Crash-safe retry/recovery, stable destination idempotency and commit receipts are implemented and verified. |
| 12 | Complete | Evidence deletion, redaction/physical purge, configurable retention and committed provenance preservation are implemented and verified. |
| 13 | Complete | Operational diagnostics, queue/retry visibility, audit/outbox coverage and privacy-safe metrics are implemented and documented. |
| 14 | Complete | Responsive/accessibility/localization/visual-regression coverage is complete, including native supported-locale catalogues and deterministic desktop/mobile visual baselines. |
| 15 | Complete | The repository-wide spec→code, code→spec and UX→backend audit found no remaining Screenshot Intake gap; current-truth docs are aligned and all applicable release gates passed on one immutable implementation candidate. |

The Screenshot Intake delivery program is closed. A fresh Phase 15 scan found no remaining planned, partial, placeholder, compatibility, stale-ownership, lifecycle, authorization, UX, test, documentation or operational requirement. Any future regression or material change reopens the affected phase rather than becoming an undocumented exception.

## Cross-phase invariants

1. Evidence ownership never expands into ownership of the extracted domain fact.
2. Public owner write contracts use scalar IDs/value objects and never accept/return foreign Eloquent models.
3. Machine attempts and human review history are immutable/auditable until their configured retention boundary.
4. No extraction field is silently promoted to domain truth without review in the first release.
5. Duplicate handling is idempotent and tenant-safe.
6. Operations recomputes accepted Bear Hunt aggregates from its report ledger and captured baseline; retries never increment blindly.
7. Evidence deletion never silently rewrites committed Operations history.
8. Extractor/provider-specific concerns stay behind contracts and versioned provenance.
9. Vue/controllers do not own extraction rules, normalization formulas, retention policy or domain validation.
10. No compatibility shims, dual schemas, dual reads/writes, temporary lifecycle states or legacy routes are retained.

## Definition of done

Delivery is closed only when:

- the same screenshot/report cannot double-count damage;
- multiple legitimate Bear Hunt reports aggregate deterministically;
- pre-existing Event Player result state is preserved as a baseline and restored after imported reports are removed;
- every machine attempt and field confidence remains historically inspectable until its configured retention boundary;
- manual correction never overwrites machine provenance;
- Player resolution cannot silently create/mutate identity;
- all accepted domain writes enter Operations through owner Actions;
- every commit crash point is idempotently recoverable;
- deleting evidence cannot cascade into an accepted result;
- authorization is tested at upload, review, commit, correction and deletion boundaries;
- spoofed/oversized/unsafe uploads fail closed;
- exact and visual duplicate detection never disclose cross-Alliance evidence;
- retention physically removes expired uncommitted evidence, removes committed binaries/sensitive raw data after their window, preserves the required committed provenance tombstone and cannot be starved by old tombstones;
- audit/observability cover the full lifecycle without raw-evidence leakage;
- supported locales contain native Screenshot Intake catalogues and pass localization/type/build checks;
- PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, accessibility/visual regression, CodeQL, dependency review, production image/container scanning, staging, clean-database install and backup/restore checks are green on one immutable release candidate.

The final Phase 15 scan is complete. This document describes the implemented current state; any later change that invalidates a definition-of-done item reopens the relevant phase and must restore the same release evidence before closeout.
