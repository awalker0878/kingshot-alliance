# Change impact guide

Status: Current — Architecture V3

Use this guide to decide which contracts and checks move with a code change.

| Change | Update/check |
| --- | --- |
| New/changed business behavior | Owning context/capability docs and behavior tests. |
| New capability | `architecture/capability-map.md`, context README, `codebase/source-layout.md` / module map and architecture structural tests. |
| Capability rename/split/merge | Replace current docs and remove superseded pages/names; update namespaces/imports/tests. |
| Context ownership/dependency change | Context map, data ownership, integration model and leakage tests. |
| Context-root technical folder introduced | Reject/move code under the owning capability. |
| Player/User/permission semantics | Authority model, owning Access/Governance capability and authorization tests. |
| Transaction/locking behavior | Consistency/transaction docs and concurrency tests. |
| Controller/middleware write behavior | Move write into owner Action; update HTTP architecture tests. |
| Cross-context mutation | Owner Actions/Workflow boundary plus persistence/import tests. |
| Cross-context read | ReadModel boundary and no-write tests. |
| New async side effect/event | Integration/outbox docs, owner capability and idempotency/retry tests. |
| Communications change | Confirm delivery remains generic and source-domain semantics stay with source owner. |
| New environment/dependency | Operations configuration/runtime/recovery and security impact. |
| Security/trust-boundary change | Governance security requirements plus focused architecture/context docs. |
| Production status change | `production-approval.md` after accountable evidence. |

Do not keep old architecture documentation as migration history after the current contract changes.