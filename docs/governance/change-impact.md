# Change impact guide

Status: Current

Use this guide to decide which documentation and reviews should change with code.

| Change | Update/check |
| --- | --- |
| New/changed business invariant | Owning `architecture/contexts/...` document; tests. |
| Context ownership/dependency changes | `architecture/context-map.md`, capability/data maps and decision record if durable. |
| Physical namespace/module move only | `codebase/module-map.md` / source docs; architecture only if ownership changed. |
| Player/User/permission semantics | `architecture/authority-model.md`, owning context, security requirements, authorization tests. |
| Transaction/locking behavior | Architecture consistency doc + codebase transaction doc + concurrency tests. |
| New async side effect/event | Integration/outbox docs, owning capability, queue/idempotency tests, Reference event catalogue. |
| New environment/dependency | Operations configuration/runtime/recovery + security/production evidence impact. |
| New user-facing capability | Product catalogue/experience + owning architecture capability. |
| New deployment/recovery procedure | Operations runbook/release docs. |
| Security/trust-boundary change | Governance security requirements plus focused architecture/context material. |
| Production status change | `governance/production-approval.md` only after accountable evidence. |

Avoid documentation churn for private refactors that leave documented contracts unchanged.