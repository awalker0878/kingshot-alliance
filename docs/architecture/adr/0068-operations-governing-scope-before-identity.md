# ADR-0068: Operations governing scope before identity

Status: Accepted

## Context

Event and Territory mutations acquired actor or plan rows before their governing lifecycle and role authority. Governance now protects role changes with the Kingdom barrier, making the opposing acquisition order a concrete deadlock risk. Player Event management also locked every active target roster even though canonical membership allows the manager only one active Alliance. Territory layer and Governor references can introduce lower-order locks after the plan is already protected.

## Decision

Operations retains Event and Territory policy and mutations. GameWorld and Alliance retain canonical identity, lifecycle and authority facts. Event type scope is protected first. Alliance Event writes acquire the Alliance then active Kingdom before the actor; Kingdom Event writes acquire the Kingdom before the actor. Player Event management routes through only the actor’s canonical active Alliance membership, protects that Alliance and membership through its owner query, then protects the active target Kingdom and locks the unique actor/target identities in sorted order. It revalidates the target route and locks only the corresponding target roster. Read authorization uses the same one-membership candidate and current canonical Kingdom checks. The obsolete all-target-rosters write query is removed.

Territory writes read routing facts, acquire current owning scope and authority before the actor and plan, then reject route drift. Creation uses the same scope acquisition service. Event attachment and detachment acquire Event scope before occurrence rows; attachment checks that the plan has the same owning scope before it can acquire any foreign scope.

Linked Territory Alliances and Governors are lower-order references acquired after the owning plan. Their owners provide current NOWAIT queries. PostgreSQL lock contention becomes an actionable validation error and rolls back the entire owner transaction; retry reacquires every current fact. The transaction is never continued after a failed lock. Other database errors are not suppressed. King Perk commands inherit the corrected Event state acquisition. Reminder preparation already acquires active Kingdom shared scope before each recipient and retains its bounded per-recipient transaction.

## Verification

KingdomScopeOrderingTest exercises actual Event creation/cancellation, Territory creation/archive and King Perk plan creation against actual administrator withdrawal in both transaction orders. PlayerEventScopeOrderingTest covers both owner roster-removal orders and a target with 1,001 unrelated roster records while keeping manager queries bounded. TerritoryScopeOrderingTest covers three linked-reference contention/retry paths, changed routing facts and actual Event attachment/detachment ordering, including early foreign-scope rejection. Existing Event profiles, Territory lifecycle/import and King Perk suites remain required. HARD-127 records executed evidence; HARD-128 separately tracks atomic clone/import/restore composition.
