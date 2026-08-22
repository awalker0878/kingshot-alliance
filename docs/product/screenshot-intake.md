# Screenshot Intake

Status: Active delivery contract — 2026-08-22

Screenshot Intake turns user-provided KingShot screenshots into reviewed domain commands without transferring ownership of the resulting game data to the intake pipeline. The first supported evidence type is the **Bear Hunt battle report** because its value, validation boundary and destination result model are concrete.

This document is the product implementation contract. Delivery continues until every non-evidence-gated requirement below is implemented, tested, documented and reflected as current product truth.

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

`Operations/Results` owns accepted Bear Hunt battle reports, report entries and all derived Event result state. Cross-context writes use scalar IDs/value objects through owner Actions. `app/Workflows` coordinates the commit handshake but owns no business persistence.

The original evidence is never rewritten. Any crop, rotation, resize, contrast adjustment or other preprocessing is a derived representation with its own checksum and source relationship.

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
10. A commit preview shows the concrete Operations effect.
11. The reviewer commits the approved revision.
12. A workflow sends a scalar Bear Hunt report command to `Operations/Results`.
13. Operations validates current authority/Event scope, persists the report idempotently, recomputes owned result aggregates and returns a scalar receipt.
14. Evidence records the receipt. Retrying after an interrupted acknowledgement returns the same result rather than double-counting damage.

## Lifecycle

Evidence lifecycle states are explicit. The supported progression is:

`uploaded → classifying → classified → extracting → needs_review → approved → committing → committed`

Exceptional/terminal states include `unsupported`, `failed`, `rejected`, `duplicate`, `delete_pending`, `deleted`, `purge_pending`, and `purged` as applicable. Machine retries create new immutable attempts; they never overwrite previous attempts.

## Bear Hunt extraction contract

The first extractor is intentionally narrow. It may commit only fields proven by fixture screenshots and visible game evidence. Initial review candidates are:

- report occurrence/timestamp when visibly available;
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

1. **Exact** — SHA-256 equality inside the authorized Alliance/evidence scope. Exact duplicates are rejected or reused before extraction.
2. **Visual** — perceptual similarity for resized, recompressed or cropped screenshots. This creates a review warning; it is not an automatic merge.
3. **Semantic** — a deterministic fingerprint after review using stable Bear Hunt report meaning such as occurrence, visible report time when available, resolved Player IDs, ranks and damage values. A semantic collision blocks duplicate commit until explicitly resolved by supported product behavior.

Duplicate checks must not disclose the existence of evidence from an unauthorized Alliance or tenant.

## Operations commit contract

`Operations/Results` receives a scalar command containing the actor Player ID, occurrence ID, source evidence/commit attempt IDs, visible report time when available, deterministic report fingerprint and reviewed entry values.

Operations must:

- reacquire current authority in its transaction;
- verify the occurrence belongs to a Bear Hunt Event with compatible scope/capability;
- validate every referenced Player against the Event target;
- reject/reuse an already accepted idempotency key/fingerprint;
- persist an immutable Bear Hunt battle-report ledger entry and its Player rows;
- recompute the owned Bear Hunt result aggregate from accepted reports rather than incrementing blindly;
- populate only metrics supported by reviewed evidence;
- return a scalar receipt with accepted report identity and affected result identities/counts.

Evidence cannot directly write `EventPlayerResult` or any Operations model.

## Commit recovery

Cross-context commit uses an explicit handshake:

`Evidence BeginCommitAttempt → Workflow BuildReviewedCommand → Operations RecordBearHuntBattleReport → Evidence MarkCommitSucceeded`

The destination idempotency key is stable for the immutable approved review revision/commit attempt. If Operations commits and the caller crashes before Evidence records success, retry returns the existing destination receipt and does not duplicate damage.

## Deletion and retention

Evidence deletion and Operations correction are separate capabilities.

- Deleting/purging evidence does not cascade into an accepted Bear Hunt result.
- Correcting/removing an accepted Bear Hunt report requires an audited `Operations/Results` owner Action and deterministic recomputation.
- Retention policy is configurable and enforced by a scheduled owner Action/Job.
- Binary/image payloads may be purged before minimal committed provenance/tombstones.
- A committed record retains enough immutable metadata to explain which evidence/review/commit produced the Operations report after the binary is purged.

Initial retention defaults may differ by lifecycle state, but policy values must live in configuration/operations documentation rather than Vue/controllers.

## Security and privacy

Uploads use private storage only. The pipeline validates allowlisted MIME types and size, verifies actual MIME, performs the repository media security scan, computes SHA-256, generates non-user-controlled storage names, and removes stored bytes if persistence fails. Raw provider responses and logs must not leak screenshot content, credentials, or cross-tenant duplicate information.

Authorization is checked before expensive work where useful and reacquired at each protected write boundary. Jobs and workflows carry scalar IDs, never serialized Eloquent authority models.

## UX requirements

The first entry point is the Bear Hunt occurrence/results workflow, not a generic AI page. The UI provides:

- upload/progress/failure/retry states;
- screenshot preview with accessible alternative context;
- extracted-field table/form that remains usable without image-only interaction;
- confidence presentation that does not rely on color alone;
- Player matching and explicit unresolved/excluded states;
- manual correction with validation;
- exact/visual/semantic duplicate messaging;
- commit preview showing current value, imported contribution and resulting aggregate where applicable;
- accessible destructive confirmation for evidence deletion;
- keyboard operation, responsive layout and localized strings;
- stable action receipts for upload, review, commit, retry and deletion.

## Observability

Audit/outbox/diagnostic events cover material lifecycle transitions including upload accepted/rejected, classification/extraction attempted/failed, review approved, duplicate detected, commit started/succeeded/failed, destination deduplication, evidence deletion and retention purge. Operational metrics include queue age, attempt latency, extraction failure rate, review correction rate, duplicate rate, commit failure rate and retention failures without exposing raw screenshot contents.

## Delivery phases

A phase is `Complete` only when its behavior, authorization, persistence, UX, accessibility, localization, observability, tests and current-truth documentation are complete and applicable repository gates pass.

| Phase | Status | Outcome / exit condition |
| --- | --- | --- |
| 1 | Complete | Product contract and architecture ownership are documented before application-code changes. |
| 2 | Planned | Secure evidence upload, immutable source metadata, private storage, scan/checksum, exact duplicate boundary and lifecycle persistence. |
| 3 | Planned | Versioned screenshot classification with immutable attempts, queued execution and unsupported/failure UX. |
| 4 | Planned | Bear Hunt battle-report extractor with fixture-proven narrow schema and deterministic normalization. |
| 5 | Planned | Immutable field-level confidence/provenance and extraction history. |
| 6 | Planned | Review revisions, Player resolution, manual correction/exclusion and commit eligibility rules. |
| 7 | Planned | Exact, visual and semantic duplicate detection with tenant-safe disclosure behavior. |
| 8 | Planned | Commit preview and authoritative validation of the reviewed command. |
| 9 | Planned | Workflow-based scalar cross-context commit into `Operations/Results` with no foreign persistence writes. |
| 10 | Planned | Operations-owned Bear Hunt report ledger, entries, deterministic recomputation and idempotent aggregation. |
| 11 | Planned | Crash-safe retry/recovery, stable commit receipts and destination deduplication. |
| 12 | Planned | Evidence deletion, redaction/purge and configurable retention without cascading domain deletion. |
| 13 | Planned | Operational diagnostics, queue/retry visibility, audit/outbox coverage and privacy-safe metrics. |
| 14 | Planned | Responsive/accessibility/localization/visual-regression completeness for every material state. |
| 15 | Planned | Repository-wide capability audit, removal of TODO/scaffolding/duplicate paths, current-truth docs and full release-gate closeout. |

## Cross-phase invariants

1. Evidence ownership never expands into ownership of the extracted domain fact.
2. Public owner write contracts use scalar IDs/value objects and never accept/return foreign Eloquent models.
3. Machine attempts and human review history are immutable/auditable.
4. No extraction field is silently promoted to domain truth without review in the first release.
5. Duplicate handling is idempotent and tenant-safe.
6. Operations recomputes accepted Bear Hunt aggregates from its report ledger; retries never increment blindly.
7. Evidence deletion never silently rewrites committed Operations history.
8. Extractor/provider-specific concerns stay behind contracts and versioned provenance.
9. Vue/controllers do not own extraction rules, normalization formulas, retention policy or domain validation.
10. No compatibility shims, dual schemas, dual reads/writes or temporary legacy routes are retained.

## Definition of done

Delivery is closed only when:

- the same screenshot/report cannot double-count damage;
- multiple legitimate Bear Hunt reports aggregate deterministically;
- every machine attempt and field confidence remains historically inspectable until its configured retention boundary;
- manual correction never overwrites machine provenance;
- Player resolution cannot silently create/mutate identity;
- all accepted domain writes enter Operations through owner Actions;
- every commit crash point is idempotently recoverable;
- deleting evidence cannot cascade into an accepted result;
- authorization is tested at upload, review, commit, correction and deletion boundaries;
- spoofed/oversized/unsafe uploads fail closed;
- retention removes binary objects and preserves the required committed provenance tombstone;
- audit/observability cover the full lifecycle without raw-evidence leakage;
- PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, accessibility/visual regression, CodeQL, dependency review, production image/container scanning, staging, clean-database install and backup/restore checks are green on the immutable release candidate.

After Phase 15, perform a fresh repository-wide scan against this document. Any missing workflow, edge state, authorization path, lifecycle transition, test, operational requirement or contradictory current documentation reopens the relevant phase. Stop only when this document describes implemented current state rather than future intent.