# Screenshot Intake application contracts

Status: Current for Bear Hunt and Transfer participant Screenshot Intake.

Screenshot Intake is an authenticated web capability. It does not expose a public API or webhook contract. Routes are application boundaries over owner actions; they do not transfer persistence ownership to controllers or read models.

## Supported evidence families

1. **Bear Hunt battle report** — one explicit Bear Hunt screenshot kind, reviewed and committed to `Operations/Results`.
2. **Transfer participant evidence** — five explicit versioned screenshot kinds, reviewed and committed to `GameWorld/KingdomTransfers`.

There is no generic Transfer OCR schema or generic evidence field bag. Transfer schemas are registered explicitly by kind/version and only schema-supported fields may be extracted or committed.

## Ownership boundary

`Intelligence/Evidence` owns uploaded source evidence, security scanning, provenance, OCR/provider and classification/extraction attempts, extracted-field confidence, review revisions, exact/visual/semantic duplicate decisions, commit-attempt history, retry/recovery, destination receipts, deletion/redaction and retention.

`Operations/Results` owns accepted Bear Hunt battle reports, report entries, result baselines, and the recomputed Event Player result projection.

`GameWorld/KingdomTransfers` owns Transfer Plans, participants, Transfer Windows, official Transfer Groups, target-Kingdom conditions, Governor Transfer observations, validity/freshness, conflicts, and eligibility calculations.

Only scalar IDs, primitive values, arrays/value objects and scalar receipts cross context boundaries. No Eloquent model crosses from Evidence to a destination owner or back.

## Bear Hunt web routes

All Bear Hunt routes require authentication, authenticated-session validation, verified email, an active Player context, and Bear Hunt management authorization. Mutations that resolve a semantic collision, commit domain state, or delete retained evidence additionally require recent password confirmation.

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/events/{occurrence}/screenshot-intake` | Render the authorized Screenshot Intake workspace for one Bear Hunt occurrence. |
| `POST` | `/events/{occurrence}/screenshot-intake` | Upload one private screenshot and start classification. |
| `GET` | `/events/{occurrence}/screenshot-intake/{evidence}/image` | Stream the retained private source image with no-store headers. |
| `PUT` | `/events/{occurrence}/screenshot-intake/{evidence}/review` | Persist a new immutable review revision over one completed extraction. |
| `POST` | `/events/{occurrence}/screenshot-intake/{evidence}/retry` | Retry terminal failed processing by creating new attempt history. |
| `POST` | `/events/{occurrence}/screenshot-intake/reviews/{review}/resolve-duplicate` | Record an audited semantic-collision resolution. |
| `POST` | `/events/{occurrence}/screenshot-intake/reviews/{review}/commit` | Commit an approved review through Evidence into `Operations/Results`. |
| `DELETE` | `/events/{occurrence}/screenshot-intake/{evidence}` | Delete/redact retained evidence without deleting accepted Bear Hunt result state. |

## Transfer participant web routes

All Transfer Evidence routes require authentication, authenticated-session validation, verified email, Alliance context, and current Transfer management authorization. Reads re-resolve Plan/participant scope. Every protected mutation below also requires recent password confirmation and re-resolves Alliance, Plan, participant, Transfer Window and current target scope as applicable.

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/alliance/transfers/{plan}/participants/{participant}/evidence` | Return the participant-scoped Evidence summary plus the five registered schema contracts for the review panel. |
| `GET` | `/alliance/transfers/{plan}/participants/{participant}/evidence/{evidence}/image` | Stream the retained private screenshot to an authorized manager with no-store headers. |
| `GET` | `/alliance/transfers/{plan}/participants/{participant}/evidence/reviews/{review}/preview` | Derive current-versus-after eligibility using the owner evaluator without persisting hypothetical eligibility. |
| `POST` | `/alliance/transfers/{plan}/participants/{participant}/evidence` | Upload one private screenshot with an expected screenshot class and start independent classification. |
| `POST` | `/alliance/transfers/{plan}/participants/{participant}/evidence/{evidence}/review` | Persist an immutable typed Transfer review revision over one completed extraction. |
| `POST` | `/alliance/transfers/{plan}/participants/{participant}/evidence/reviews/{review}/resolve-duplicate` | Record an audited resolution for a semantic duplicate. |
| `POST` | `/alliance/transfers/{plan}/participants/{participant}/evidence/reviews/{review}/commit` | Commit one approved review through the matching KingdomTransfers destination Action. |
| `POST` | `/alliance/transfers/{plan}/participants/{participant}/evidence/{evidence}/retry` | Retry terminal failed classification/extraction while preserving prior attempts. |
| `DELETE` | `/alliance/transfers/{plan}/participants/{participant}/evidence/{evidence}` | Redact/delete Evidence-owned source material without deleting accepted Transfer owner history. |

Route parameters are not authorization. Owner/application services scope every read and mutation independently.

## Transfer schema registry

The v1 registry contains exactly five supported screenshot kinds.

| Evidence kind | Schema version | Required reviewed meaning | Optional reviewed meaning | Destination Action |
| --- | --- | --- | --- | --- |
| `transfer_governor_status` | `transfer-governor-status/1` | `governor_power`, reviewer-confirmed `observed_at`, explicit `valid_until` | none | `RecordGovernorStatusEvidence` |
| `transfer_score_passes` | `transfer-score-passes/1` | `transfer_score`, `transfer_passes_available`, `transfer_passes_required`, `observed_at`, `valid_until` | none | `RecordTransferScorePassEvidence` |
| `transfer_invitation` | `transfer-invitation/1` | `invitation_status`, current target scope, `observed_at`, `valid_until` | fixture-proven visible target Kingdom number for reconciliation | `RecordTransferInvitationEvidence` |
| `transfer_target_kingdom_rules` | `transfer-target-kingdom-rules/1` | visible target Kingdom number, `power_cap`, `observed_at` | `kingdom_classification=ordinary|leading|unknown` when fixture-proven | `RecordTransferKingdomRulesEvidence` |
| `transfer_official_group` | `transfer-official-group/1` | `official_group_identifier`, complete explicitly visible Kingdom membership, `observed_at` | none in v1 | `RecordOfficialTransferGroupEvidence` |

The registry also records the classifier threshold, field-confidence threshold, fixture corpus identifier, normalizer/extractor identity and destination Action. A new field is not accepted merely because OCR produces it; the schema must explicitly support it and fixture tests must prove it.

## Classification and extraction

The user-selected Transfer screenshot class is stored as `expected_kind` and is only a routing expectation. Classification independently chooses the supported kind. A mismatch is surfaced as unsupported/needs review and does not run the selected class's extractor as truth.

Extraction stores raw text, normalized candidate value, data type, confidence, bounding region and warnings. Human correction never rewrites that machine record.

Important v1 negative rules:

- `transfer_passes_required` is never calculated from Transfer Score;
- adjacent numbers are not promoted into missing schema fields;
- `in_game_rules_verified` is not supported by any v1 Transfer schema;
- partial/hidden official-group membership is not inferred;
- novel invitation wording is not coerced into the nearest invitation enum;
- Governor Power and target Power Cap remain separate fields/schemas.

## Transfer review command

The review boundary accepts one completed extraction plus reviewer-confirmed source time and only the typed fields relevant to the evidence kind. The controller accepts nullable fields because one HTTP endpoint serves all five schemas; `SaveTransferEvidenceReview` applies the active schema's required/optional contract and rejects unsupported meaning.

Common review inputs:

- `extraction_attempt_id` — completed extraction belonging to the Evidence;
- `observed_at` — required reviewer-confirmed or fixture-proven source observation time;
- `valid_until` — required for Governor status, score/pass and invitation observations because those mutable facts need an explicit owner freshness boundary.

Schema-specific inputs are typed integers/enums/strings or the official-group Kingdom-number list. Non-negative numeric invariants are enforced. Target-specific screenshots are reconciled against the participant's current target Kingdom; target-rules screenshots require the target number to be visibly reviewed.

Review approval also verifies:

- the Evidence is in the same Alliance/Plan/participant scope;
- the classified kind equals the expected supported kind;
- extraction belongs to the Evidence and exact schema version;
- classification confidence meets the schema threshold;
- extracted field keys are all permitted by the registered schema;
- the scope snapshot has not changed while review is being persisted.

## Semantic duplicate contract

Each Transfer schema creates a deterministic SHA-256 semantic fingerprint from its schema version, stable reviewed meaning, appropriate Transfer Window/participant/target scope and observation boundary.

A semantic collision with another approved/blocked review prevents accidental repeated commit. Resolution is an explicit audited review action and does not change the fingerprint or destination idempotency key model.

A genuinely newer observation remains importable because its reviewed state and/or source observation boundary differs.

Exact binary duplicates and visual similarity are separate controls. Exact duplicate disclosure is limited to the authorized Alliance + Plan + participant + expected-schema scope. Visual similarity is advisory and remains reviewable.

## Transfer destination command contracts

The public owner Actions accept scalar values only and all share owner-side locking/authorization/idempotency support.

Conceptually:

```text
RecordGovernorStatusEvidence(..., governorPower, observedAt, validUntil) -> TransferEvidenceReceipt
RecordTransferScorePassEvidence(..., transferScore, passesAvailable, passesRequired, observedAt, validUntil) -> TransferEvidenceReceipt
RecordTransferInvitationEvidence(..., invitationStatus, observedAt, validUntil) -> TransferEvidenceReceipt
RecordTransferKingdomRulesEvidence(..., powerCap, classification, observedAt) -> TransferEvidenceReceipt
RecordOfficialTransferGroupEvidence(..., officialLabel, kingdomNumbers[], observedAt) -> TransferEvidenceReceipt
```

Every destination Action:

- reacquires current actor/Alliance authority in an owner transaction;
- locks/re-resolves current Plan, participant, Transfer Window and target where material;
- compares current scope with the approved review snapshot;
- validates Evidence provenance through the Evidence lookup contract;
- validates typed owner invariants;
- reuses owner-internal observation/condition/group writers;
- appends owner history instead of overwriting historical observations;
- records/returns a scalar receipt under a unique destination idempotency key.

`RecordTransferScorePassEvidence` writes its three related observations in one outer transaction. If any one value fails validation, none of the three observations or the destination receipt survives.

## Freshness and eligibility preview

Evidence does not set a hidden TTL. It persists explicit reviewed source/validity information; `GameWorld/KingdomTransfers` decides whether a fact is current, stale, conflicting, non-authoritative or unknown.

The preview boundary uses the existing KingdomTransfers eligibility evaluator. It substitutes only reviewed facts in memory and leaves every other current owner fact unchanged. In particular, a v1 preview cannot create or change `in_game_rules_verified`; missing verification therefore continues to yield the owner's `needs_verification`/requirement state when otherwise applicable.

## Destination idempotency and crash recovery

Semantic duplicate detection and destination idempotency are separate.

Evidence derives one stable destination idempotency key from the immutable approved review. KingdomTransfers stores a receipt under that unique key. If the owner transaction succeeds but Evidence acknowledgement fails, a normal retry uses the same key. After current actor authorization, the owner returns the existing receipt rather than appending observations/conditions/group history again; Evidence can then record the recovered acknowledgement.

Failed Evidence commit attempts remain immutable history. Reusing a destination key for different approved meaning is rejected.

## Error and privacy semantics

Validation failures use normal Laravel validation errors. Authorization failures are denied by current owner services. A retained image that has been lifecycle-deleted returns unavailable rather than recreating or exposing the binary.

Processing failures are durable Evidence attempt outcomes with privacy-safe failure codes. Raw provider/OCR payloads, image hashes, screenshot filenames and reviewed private values are not emitted by diagnostics or cross-tenant duplicate messages.