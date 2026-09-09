# GameWorld — Kingdoms

Status: Current — Architecture V3

Implementation target: `app/Contexts/GameWorld/Kingdoms`

Kingdoms owns canonical neutral Kingdom and game-side Alliance identity/reference facts. It anchors Player, Alliance, Operations and Intelligence records without absorbing the policy or observations owned by those contexts.

## Boundary

Kingdoms owns:

- Kingdom identity, numeric reference and lifecycle state;
- neutral game-side Alliance identity within a Kingdom;
- current Alliance name/tag and stable game Alliance ID when known;
- temporal Alliance identity history and provenance metadata;
- active versus historical reference contracts;
- explicit canonicalization/reconciliation of duplicate neutral Alliance identities;
- integrity diagnostics for the identity graph.

Kingdoms does not own Alliance membership, Governance authority, Intelligence observations/evidence, Operations events, KingdomMaps spatial truth, or tracking/diplomacy policy merely because those records reference a Kingdom or game-side Alliance.

## Identity invariants

- A Kingdom has an internal ULID and a unique positive game-facing number.
- `KingdomAlliance` always belongs to exactly one Kingdom.
- `game_alliance_id`, when known, is stronger identity evidence than mutable name/tag attributes.
- Name and tag are mutable facts, never automatic identity keys.
- Unknown stable IDs deliberately produce separate neutral Alliance rows; similarity may surface reconciliation candidates but may never auto-merge them.
- An assigned stable game Alliance ID cannot be changed in place.
- Archived identities remain historically readable but are rejected for new operational work.
- An archived Kingdom cannot receive or mutate operational Alliance identities.
- Archiving a Kingdom atomically archives its active game-side Alliance identities. Restoring a Kingdom does not silently restore those child identities.
- Reconciled aliases remain permanently resolvable for historical foreign keys, but new operational work resolves to the direct active canonical identity.
- Reconciliation is explicit, same-Kingdom, transactional, provenance-bearing and audited.

## Reference semantics

Historical reads use `find()` / `require()` and may return archived or reconciled aliases.

Operational writes use active/canonical contracts:

- `KingdomReferenceQuery::findActive()` / `requireActive()`;
- `KingdomAllianceReferenceQuery::findActive()` / `requireActive()`;
- `requireCanonical()` when an old identifier must be translated;
- `requireActiveCanonical()` when an operational consumer accepts an identifier that may have become an alias.

Changing the historical methods to globally filter archived data is prohibited because event, observation and audit history must remain renderable.

## Alliance identity history

`kingdom_alliance_identity_history` is the temporal record of current-name, current-tag and stable-ID facts. Exactly one open interval may exist for a direct current identity. Mutations close the previous interval and append a new interval in the same transaction.

History carries lightweight provenance (`source_type`, source reference, observed time, optional confidence and reason). Raw screenshots, observation payloads and evidence remain in their owner contexts.

## Reconciliation

`canonical_kingdom_alliance_id` makes an alias point to the canonical neutral identity while preserving the alias row for historical foreign keys. `kingdom_alliance_reconciliations` is an append-only record of the explicit decision.

Reconciliation:

1. discovers and locks the canonical Kingdom, then locks both direct identities in ID order within that scope;
2. requires the same active Kingdom and an active canonical target;
3. rejects conflicting stable game IDs;
4. transfers a late stable ID to the canonical identity when safe;
5. closes the duplicate's current identity interval;
6. archives the duplicate and records its canonical target;
7. records provenance and audit evidence.

No fuzzy name/tag process may call reconciliation automatically.

## Lifecycle

Lifecycle changes go through `ArchiveKingdom`, `RestoreKingdom`, `ArchiveKingdomAlliance` and `RestoreKingdomAlliance`. Resolution never implicitly restores archived state, and reconciled aliases cannot be restored as independent identities.

Neutral Alliance update, restore, archive and reconciliation acquire the Kingdom before child identity locks and revalidate expected/discovered scope. Kingdom archival reads at most 200 active children per ascending-ID batch under its parent lifecycle barrier, preserving individual audit records and an exact total count. All batches and the parent transition remain one transaction; retries produce no additional archival events. See [ADR-0030](../../adr/0030-kingdom-first-neutral-identity-locks-and-bounded-archival.md).

## Integrity

`KingdomsIntegrityQuery` reports invalid lifecycle combinations, active aliases, missing/drifting current history, multiple open history intervals, canonical cycles and unresolved similarity candidates. Candidate diagnostics are informational; they are not proof of duplicate identity.

## Fresh-deployment schema

This application is not yet deployed with production data. The authoritative create migration therefore defines the completed schema directly. There are no compatibility shims, legacy backfills or transitional identity semantics.
