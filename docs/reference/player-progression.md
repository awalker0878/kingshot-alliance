# Player progression observations

Status: Current

Player progression is an append-only intelligence view over roster observations. It is not authoritative game state and it does not replace Alliance membership.

## Capture paths

- Officers can record a manual scout finding from a Governor history page.
- Controlled CSV imports preview identity matches and row validation before an atomic commit.
- Approved ingestion adapters can promote observations only with batch, adapter, identity, and payload provenance.

Every accepted observation records the observed Governor name, power, optional free-text progression label, optional Alliance tag, capture time, source, and actor or machine provenance. Repeating an identical accepted finding is idempotent.

## History and change semantics

- History is ordered by capture time, newest first.
- Each visible observation is compared only with the immediately preceding observation for that Governor.
- Power change uses signed 64-bit-safe decimal arithmetic.
- Name, progression-label, and Alliance-tag changes show their observed before/after values.
- A change means the observations differ; it does not claim when the in-game change occurred.
- Current/stale/missing freshness is based on the latest capture time. Missing observations are never treated as zero.

The Governor page shows up to 250 observations. A boundary observation is loaded only to calculate the oldest visible row's consecutive change; it is not displayed. The UI says when earlier history exists.

## Trust boundary

Progression labels remain free text because the application has no verified, versioned furnace/level catalogue. Cost tables, upgrade requirements, or normalized game-level claims must not be derived from these labels.
