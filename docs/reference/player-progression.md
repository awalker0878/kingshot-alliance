# Player progression observations

Status: Current

Player progression is an append-only intelligence view over roster observations. It is not authoritative game state and it does not replace Alliance membership or the `GameWorld/Progression` factual catalogue.

## Capture paths

- Officers can record a manual scout finding from a Governor history page.
- Controlled CSV imports preview identity matches and row validation before an atomic commit.
- Approved ingestion adapters can promote observations only with batch, adapter, identity, and payload provenance.
- Normalized Hero observations may reference a published `GameWorld/Progression` dataset/hero identity when the source actually supports that normalization.

Every accepted observation records the observed Governor name, power, optional free-text progression label, optional Alliance tag, capture time, source, and actor or machine provenance. Repeating an identical accepted finding is idempotent.

Normalized Hero observations additionally retain their progression dataset identity/checksum and observed Hero values. A missing Hero from an incomplete observation is not evidence that the Governor does not own that Hero.

## History and change semantics

- History is ordered by capture time, newest first.
- Each visible observation is compared only with the immediately preceding observation for that Governor.
- Power change uses signed 64-bit-safe decimal arithmetic.
- Name, progression-label, and Alliance-tag changes show their observed before/after values.
- A change means the observations differ; it does not claim when the in-game change occurred.
- Current/stale/missing freshness is based on the latest capture time. Missing observations are never treated as zero.
- A later factual progression release never rewrites a historical observation pinned to an earlier release/checksum.

The Governor page shows up to 250 observations. A boundary observation is loaded only to calculate the oldest visible row's consecutive change; it is not displayed. The UI says when earlier history exists.

## Trust boundary

Free-text progression labels remain valid for observations that cannot be normalized safely. Normalized catalogue references come only from a published, versioned `GameWorld/Progression` release; they do not make the observation authoritative game state.

Cost tables, upgrade requirements, recommendations or calculator truth are never derived from an officer's free-text label. The factual catalogue may expose sourced reference values while a calculator remains evidence-gated until its individual numeric family satisfies the stricter calculator evidence contract.
