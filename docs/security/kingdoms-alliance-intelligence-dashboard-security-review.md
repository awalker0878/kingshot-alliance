# KINGDOMS-003 Slice D intelligence dashboard security review

**Scope:** `KINGDOMS-003` Slice D / `K3-P5`  
**Status:** Validated against runtime `a9d2e22ea1c710bc72f4dc8824a70e15dda04e75`

## Review objective

Validate that the Slice D intelligence dashboard composes tenant-owned tracking, observations, explicit diplomacy and manager-private contact diagnostics into descriptive read-only intelligence without creating a cross-tenant disclosure path, private-data leakage, scoring/recommendation engine, or new mutation/integration surface.

## Authorization and active-Alliance tenancy

The dashboard route requires authenticated/verified active-Alliance context and `alliance.view`.

All projection queries start with the active `Alliance` ID before selecting tracked alliances, accepted observations or contacts. A shared global neutral `KingdomAlliance` reference does not grant access to another tenant's tracking, observations, diplomacy, contacts or derived intelligence.

`kingdoms.manage` controls only whether manager-private aggregate contact diagnostics and links to existing manager workspaces are included. The dashboard itself is read-only, so it does not require recent password confirmation and adds no privileged mutation endpoint.

## Object and query isolation

Slice D accepts no tracking/contact/object ID from the user for aggregate selection. The only user-controlled inputs are validated fixed-vocabulary filters and sort direction.

Allowed filters:

- tracking: `all`, `active`, `archived`;
- freshness: `all`, `current`, `stale`, `missing`;
- diplomacy: `all` plus the six locked diplomacy states;
- sort: `name`, `tag`, `power`, `members`, `age`, `diplomacy`; and
- direction: `asc`, `desc`.

These values do not become arbitrary SQL identifiers. Detail-row sorting occurs against a fixed in-memory mapping after tenant-scoped data is loaded. This prevents arbitrary column/order injection.

## Accepted-observation integrity

Dashboard observation points:

- require matching active Alliance ownership;
- exclude invalidated rows;
- exclude observations captured after dashboard `asOf`;
- select latest/prior points deterministically by `captured_at`, then ULID; and
- use bounded 7-day and 30-day baseline windows without interpolation.

An invalidated observation cannot remain the current/trend source. An older baseline outside the bounded window cannot be silently used to manufacture a trend.

Missing values remain null/missing. Recorded zero remains zero.

## Diplomacy safety

Diplomacy displayed on the dashboard comes only from the explicit current relationship row.

Review/expiry timestamps can derive a `needsReview` boolean but cannot mutate relationship state. Observations, power/member changes, trends and contacts never infer NAP/ally/rival state and never trigger a transition.

Member-safe intelligence does not load or expose diplomacy terms, rationale or actor provenance.

## Contact privacy

Contact text remains manager-private and is not copied into the dashboard projection.

Manager diagnostics are limited to:

- active contact count;
- verification-due count;
- latest active verification timestamp; and
- the existing private contact-workspace URL.

The projection does not include contact IDs, display names, roles, channel types, handles or manager notes.

Ordinary members receive no contact diagnostics or contact-workspace URL at all.

This prevents Slice D from turning the private coordination directory into a member-visible or cross-tenant directory.

## No scoring, ranking or recommendation engine

Slice D calculates factual deltas only. It has no:

- threat score;
- desirability score;
- target score;
- composite score;
- combat prediction;
- punitive score;
- best/worst alliance label;
- recommended action;
- diplomacy recommendation;
- attack recommendation;
- transfer recommendation; or
- automated negotiation behavior.

Optional factual sorting is explicitly navigation only. Missing sort values remain missing and are placed after supported values rather than imputed.

## No new persistence or side effects

Slice D introduces no migration, new table, materialized score, cache-owned truth, audit event or outbox event.

The dashboard performs reads only. Existing mutation actions remain the only source of Kingdoms audit/outbox evidence.

No scheduler, crawler, scraper, OCR, bot or automated ingestion process is added.

## API and integration boundary

The dashboard is first-party web UI only.

Slice D adds no `/api/v1` Kingdoms contract, OAuth/scope vocabulary, external webhook payload or generic webhook fan-out. Existing Integration policy continues to exclude `kingdoms.*` events from external webhook delivery.

## Kingdom drift and historical state

Archived or Kingdom-drifted tracking can remain readable when the user explicitly filters for it, with a historical-context indicator. The dashboard never rewrites or silently retargets captured Kingdom context.

Headline operational summaries intentionally count active tracking only.

## Performance/availability boundary

The projection is batched and bounded. It does not eager-load unbounded observation/contact history.

The validated Slice D performance test models 120 tracked alliances, 600 observations, 120 diplomacy relationships and 60 contacts, with a manager-dashboard budget of no more than 10 SELECT statements.

This gate protects against an N+1 path that could otherwise be amplified by a large tenant dataset.

## Security regression evidence

Protected validation on runtime `a9d2e22ea1c710bc72f4dc8824a70e15dda04e75` retained tests proving:

- cross-tenant aggregate isolation;
- invalidated/future observation exclusion;
- bounded trend-window behavior;
- missing-versus-zero semantics;
- member/manager contact privacy split;
- private contact text absent even from manager dashboard response payloads;
- fixed filter/sort vocabulary;
- absence of scoring/recommendation/automatic-action contracts;
- absence of public Kingdoms API/webhook exposure; and
- realistic-volume bounded query count.

Dependency Review `31414124893`, CodeQL `31414124920` and CI `31414124902` all passed. The complete CI included 353 tests / 4,452 assertions, static analysis, frontend quality/build, immutable-image staging, backup/restore and vulnerability scanning.

## Conclusion

Slice D / `K3-P5` is **Validated** as a tenant-scoped, read-only, descriptive projection over already-authorized K3 facts.

`KINGDOMS-003` remains **In progress** until `K3-P6` whole-increment hardening and acceptance. This review does not approve production cutover.
