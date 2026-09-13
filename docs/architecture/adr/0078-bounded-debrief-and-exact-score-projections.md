# ADR-0078 — Bounded Debrief and exact score projections

Status: Accepted

## Context

The Debrief materialized every scored Governor, every current attendance and Rally assignment, and every attendance/Rally row across its otherwise bounded recent-run window. A constant query count did not bound memory or payloads. Adding scores in PHP could overflow; casting SQL totals to PHP integers or exporting wide integers as JSON numbers could lose exact values. A personal result cannot disappear merely because its Governor is outside a displayed page.

## Decision

Results provides exact SQL totals and a 25-Governor catalogue, ordered by rank with null ranks last, descending score, and Governor identity. One sentinel and an actor/occurrence-scoped encrypted cursor retain the initial result-identity frontier and last rank/score/Governor position. Higher-identity insertions enter a fresh traversal; current edits/deletions remain current. The current Governor's result is independently queried and does not add an extra leaderboard row. Governor reference/provenance reads cover at most the displayed page plus that personal result.

Participation and Rallies aggregate complete current-occurrence totals in SQL. Their detail maps accept at most 26 requested Governors; no request hydrates all retained assignments. Recorded absence remains distinct from unrecorded planning state. EventAnalysis groups historical attendance by run/status and recorded Rally facts by run/role/status, including current-Governor aggregates. Its public history contract rejects more than 24 raw run IDs; the existing Debrief navigation window remains twelve runs. ReadModels retain read-only composition and current authorization.

Recorded damage, SQL totals, comparison deltas and personal-best ordering remain exact. Values within JavaScript's safe integer range retain the existing numeric JSON representation. Wider values use canonical decimal strings, including totals beyond PHP's integer range. The already locked brick/math 0.18 package is declared as a direct dependency for exact integer arithmetic; no dependency version changes. Negative or non-integral stored score aggregates reject explicitly. Absence remains null rather than zero.

The frontend formats wide decimal strings through BigInt, preserving every digit. Ordinary scores retain the existing compact presentation. Percentage changes and chart widths remain approximate display calculations; exact scores/deltas and personal-best decisions do not depend on those floating-point calculations. The shared number formatter accepts bigint as supported by Intl.NumberFormat.

Independent catalogue controls preserve summary/personal/history data, current authority and retry behavior. Empty later pages retain the complete summary and First page navigation. No owner mutation or accepted report/baseline semantics change.

## Verification and limits

Eight new database cases cover complete rank/score/null traversal and insertion frontier, 1,001-Governor result/attendance/Rally composition with bounded model hydration, totals above PHP/JSON integer ranges, adjacent wide-integer comparisons and accepted personal best, empty later pages, cursor scope, HTTP revocation and raw history overflow. Existing absence/provenance/removal/comparison cases remain active. A separate 61-Governor browser fixture checks independent personal facts, exact wide totals, complete pages, injected failure/retry and desktop/mobile overflow. Hosted execution and final containing gates remain required.

The manager-only unmatched-Evidence queue retains its existing separate contract. Its latest-attempt/field hydration is an independently recorded remaining audit finding, HARD-138; this decision does not claim to bound that queue's nested extraction history. HARD-134 still covers other Event-management collections and EventPlayerIntelligence history.
