# ADR-0079 — Event Player Intelligence history aggregates

Status: Accepted

## Context

Event Player Intelligence loaded all matching Events and ended occurrences, then hydrated retained registrations, roster members, Rally assignments, attendance and scored results. Its memory grew with history even for one Governor. In-memory score averages also lost precision near the database integer limit.

## Decision

EventAnalysis keeps the existing read-only composition and existing Event scope/time filters. Event and occurrence identities remain database subqueries. Registration, roster, Rally and attendance facts are combined with UNION ALL and grouped once per Governor/occurrence. MAX flags preserve set union, with completed taking priority over excused and absent. Unresolved commitments exclude every resolved state; an attendance completion need not imply a prior commitment.

A second query returns exact score counts, rounded numeric averages and maxima for ended occurrences of the same Event type scope. A third query selects each Governor's latest scored result using recorded time and identity as a deterministic tie-break. Wide scores use the existing exact decimal JSON contract; absent scores remain null. No retained history models are hydrated.

## Verification and limits

Three PostgreSQL cases cover crossed owner facts and outcome priority; 2,001 retained occurrences with exactly three queries and zero hydrated history models; and exact wide averages with future, other-type and foreign-Alliance exclusion. Current-Alliance attendance across Event types remains relevant to reliability. Unknown Governors retain absent metrics.

The Event management eligible-Governor collection and other nested operational catalogues remain separately open under HARD-134. This decision bounds history per requested Governor set; it does not claim that the management Governor set itself is bounded. No mutation owner or admission policy changes.
