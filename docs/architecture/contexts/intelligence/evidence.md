# Intelligence / Evidence

Status: Current — Architecture V3

`Intelligence/Evidence` owns game evidence intake and the provenance of attempts to understand that evidence. It deliberately does not own the domain facts that a reviewed screenshot may eventually create.

## Ownership

Evidence owns:

- private uploaded screenshot objects and immutable source metadata;
- source/derived-representation checksums;
- classification and extraction attempts with implementation/provider versions;
- extracted field candidates, raw text, normalized values, confidence and bounding regions;
- immutable review revisions and manual corrections/exclusions;
- Player-resolution decisions represented as scalar foreign IDs;
- exact, visual and semantic duplicate evidence decisions;
- commit attempts, idempotency keys and destination receipts;
- evidence deletion, redaction and retention lifecycle.

Evidence does not own:

- Player or Alliance identity/membership;
- Event or EventOccurrence state;
- Bear Hunt battle-report results or aggregate damage;
- any other domain state inferred from evidence.

## Boundary

A screenshot is evidence, not domain truth. Machine classification/extraction output remains candidate state until an authorized review approves a concrete revision. Cross-context commit is coordinated by `app/Workflows`; the destination owner receives scalar IDs/value objects and revalidates current authority and domain invariants in its own transaction.

For Bear Hunt, `Operations/Results` owns accepted battle-report ledgers, report entries, and the recomputed `EventPlayerResult` aggregates. `Intelligence/Evidence` records only the destination receipt and provenance link.

No foreign Eloquent model crosses the boundary. A retained `player_id`, `alliance_id`, `occurrence_id`, or destination report ID is a scalar reference and does not transfer ownership.

## Immutability

The original uploaded object is never rewritten. Preprocessing creates a derived representation with its own checksum and parent evidence identity. Classification/extraction retries append new attempts rather than updating historical output. Human corrections append review revisions and do not rewrite the machine confidence that produced the candidate.

## Idempotency and recovery

A commit attempt has a stable idempotency key for one immutable approved review revision. The destination owner treats retries as the same command and returns the existing receipt when the report was already accepted. This covers a crash after the destination transaction commits but before Evidence records success.

## Deletion and retention

Deleting Evidence does not cascade into a committed domain result. Once the destination owner accepts a report, correction/removal is an explicit audited owner action there. Evidence retention may remove binary/image payloads while retaining the minimum committed provenance/tombstone needed to explain the historical handoff.

## Shared infrastructure

Upload security is a technical concern under `Shared/Infrastructure/Uploads`. Alliance Content and Intelligence Evidence consume the same scanner contract; Intelligence does not depend on Alliance Content merely to inspect a file.
