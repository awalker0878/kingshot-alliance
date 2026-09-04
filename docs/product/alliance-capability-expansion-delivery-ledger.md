# Alliance Capability Expansion — Delivery Ledger

Status: Current complete capability

| Phase | Slice | State | Evidence |
| --- | --- | --- | --- |
| 0 | Documentation and acceptance contract | Complete | Canonical program, acceptance and delivery ledger define ownership, authority and exclusions. |
| 1 | Alliance Settings | Complete | Lifecycle owner action, officer UI, authorization/validation and audit/outbox behavior tests. |
| 2 | Specialist Role Administration | Complete | Access-owned role definition/delegation actions, system-role protections, UI and behavior tests. |
| 3 | Membership Governance History | Complete | Audit-derived member history read model, officer surface and read-model behavior coverage. |
| 4 | Roster Screenshot Intake & Reconciliation | Complete | Private Evidence upload/review, exactly-once Roster observation commit, factual reconciliation and dedicated behavior coverage. |
| 5 | Bulk Rank/Role Operations | Complete | Bounded preview/commit for rank and specialist-role changes with single-owner rechecks and behavior coverage. |
| 6 | Recruitment Re-entry Controls | Complete | Alliance-local private re-entry policy, merge/conversion enforcement, UI and behavior coverage. |
| 7 | Alliance Governance Timeline | Complete | Scope-bound audit-derived timeline, filters/cursor and dedicated read-model coverage. |
| 8 | Existing composition integration | Complete | Alliance Hall, Member Capability Profile, Command Overview and read-only Alliance Assistant consume owner/read-side facts. |
| 9 | Product-document reconciliation | Complete | Catalogue, gap analysis, frontend map, user journeys, product index and architecture capability map reconciled. |
| 10 | Verification and release closeout | Complete | Implementation candidate `faa5643fe004f6370575bdebe67c111525ec4175` passed CI #4933, Intelligence Verification #1865, Architecture V3 Verification #1938, Visual Regression #2905, CodeQL #4928 and Dependency Review #4712. The commit containing this completed ledger is documentation-only relative to that verified implementation tree and must pass the same required repository gates before merge. |

The capability remains closed only while the final PR head is green across all required repository workflows. Any regression reopens Phase 10 rather than weakening an acceptance gate.