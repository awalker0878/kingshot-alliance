# Contributions security profile

[← Contributions domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Contributions  
**Code owner:** `app/Domain/Contributions`  
**Primary security boundary:** durable Player history plus scope-authorized Alliance/Kingdom historical reporting, privileged Contributions management/export, and explainable non-destructive history

## 1. Security purpose and scope

Contributions protects Player-private lifetime history, Alliance/Kingdom-private historical reporting, Contributions-owned records/evidence, exports, scheduled reports, and explainable calculation/correction state from unauthorized disclosure or integrity loss.

Events owns Event participation/results/metrics/history facts. Contributions composes those facts for authorized reads and does not duplicate them into a second canonical ledger.

## 2. Assets and sensitive data

Assets include:

- durable Player-linked Contributions records and Event-history views;
- historical Event metrics/results and occurrence-time represented Alliance/Kingdom context exposed through authorized reporting;
- evidence references and subjective assessments/data classes;
- calculation provenance and correction/reversal history;
- data-quality flags;
- reports/exports; and
- report schedules/runs.

Personal history is private to the exact active Player. Alliance and Kingdom historical reporting can expose broad organizational participation data and is a privileged disclosure boundary.

## 3. Actors, authentication and authorization

The exact currently active Player may view that Player's own lifetime history across historical Player-, Alliance-, and Kingdom-scoped Events.

Alliance-wide history/reporting requires current active-Player authority for the exact Alliance. Contributions-owned management, approval, correction, reversal, data-quality operations, exports, and report schedules require `contributions.manage` plus required recent Identity assurance.

Kingdom-wide historical Event reporting requires current exact-Kingdom Player authority.

Historical affiliation, old rank, represented Alliance/Kingdom snapshots, record IDs, and Platform Administrator status never bypass current game-domain authorization.

## 4. Tenant and privacy boundaries

Historical ownership is not the same as current tenancy membership.

- Personal history is keyed to durable `player_id`.
- Alliance history is keyed to immutable Event `alliance_id`.
- Kingdom history is keyed to immutable Event `kingdom_id`.

A Player leaving an Alliance or transferring Kingdoms does not erase old Event facts. Current authorized organization leadership may still read organization-owned history, while former leaders lose organization-wide access when current authority is removed.

Subjective/private evidence must not be represented as unexplained objective scoring or copied into generic logs/audit/outbox payloads. Public exposure is never implied by leaderboard or Integrations support.

## 5. Trust boundaries and data flows

Material flows are:

```text
active Player → personal history composition
current Alliance authority → Alliance historical Event/report view
current Kingdom authority → Kingdom historical Event/report view
member/manager browser → Contributions-owned mutations
Events-owned facts → read-only history/report composition
Contributions schedules → Notifications due-time coordination
persisted authorized records/facts → report/export generation
```

Each receiving domain retains its own authorization and payload-minimization rules. Contributions never uses historical context snapshots as authority.

## 6. Threats, abuse cases and controls

Threats include:

- sibling Player history leakage through shared `user_id`;
- former leader retaining organization-wide history access;
- current membership filters hiding former-member historical facts;
- current Kingdom placement rewriting old Kingdom Event reporting;
- Platform Admin game-domain bypass;
- cross-target report/export access;
- unauthorized Contributions mutations;
- history tampering;
- opaque/misleading metric aggregation; and
- private evidence leakage through exports/logs.

Controls include exact active-Player identity, current Alliance/Kingdom scope authorization, immutable Event targets, durable Player IDs, non-authoritative historical snapshots, append-oriented correction/reversal state, metric compatibility semantics, bounded authorized exports, and data-quality states that do not mutate values silently.

## 7. Integrity, concurrency and idempotency

Contributions-owned corrections/reversals preserve prior records/links rather than overwriting history. Event result/metric integrity and concurrency are owned by Events under ADR 0010.

History composition is read-only with respect to Events and therefore cannot create duplicate Event source facts. Scheduled report requests use deterministic occurrence identity so scheduler retries do not create duplicate logical work.

## 8. Secrets and credential handling

Contributions owns no authentication/API/webhook secret. Evidence/history/export payloads must not contain credentials, recovery material, signing secrets, or unrelated private fields from source domains.

Generated export files are sensitive artifacts and follow scope-safe path/storage/download controls defined by shared runtime contracts.

## 9. Destructive operations, retention and deletion

Normal correction/reversal is non-destructive. Retained Event history cannot be cascade-deleted merely because a Player leaves, changes Alliance/Kingdom, becomes unclaimed, or organization leadership changes.

Report/export retention and account/Alliance lifecycle are coordinated with Platform while preserving historical business meaning and legitimate evidence obligations. Any anonymization must minimize personally identifying data without fabricating or rewriting Event ownership/context.

## 10. Auditability, observability and evidence

Privileged Contributions mutations and report/export actions are attributable where required. Event mutations remain attributable in Events. Operators distinguish source domain, Event/occurrence when applicable, immutable target, Player ID, metric/category, evidence gaps, approval/correction state, schedule/run state, and export completion.

Tests cover current authorization, historical ownership, sibling Player isolation, movement/transfer history retention, calculation/version semantics, correction/reversal history, exports/reporting, and schedule behavior. See [Security baseline](../../../security/security-baseline.md).

## 11. Residual risks and explicit non-capabilities

Authorized organization exports can legitimately concentrate private historical data, so operational storage/retention/download controls remain important beyond controller authorization. Subjective categories can still be misused socially even when technically explainable.

Contributions does not edit/duplicate Events facts, derive historical ownership from current membership, expose cross-target history without current authority, own machine credentials/webhooks, or produce an unexplained universal contribution score.

## 12. Focused reviews and related documentation

EVENT-CONTRIB-001 security coverage is defined by the Events historical ownership contract and this profile.

- [Contributions domain contract](../README.md)
- [Event history composition](../event-reconciliation.md)
- [Event contribution and historical intelligence](../../events/event-contribution-history.md)
- [Events security profile](../../events/security/README.md)
- [Notifications security profile](../../notifications/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
