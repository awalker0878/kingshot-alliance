# CA-P2 Certification — Immutable Request/Security Boundary

Status: PASS

Completed:
- Public V3 write contracts no longer accept Eloquent models.
- `PlatformWriteContext` now carries immutable account/grant identity facts rather than a live `PlatformAdministrator` model.
- Alliance-scoped controllers use `AllianceContext::scope()` identity (`playerId`, `allianceId`, `kingdomId`, `membershipId`) instead of removed live-model accessors.
- Redundant Intelligence roster write facades were removed; roster mutations now call the Alliance/Membership owner actions with scalar IDs.
- Platform integration and event-administration write calls now pass scalar route/authority IDs.

Executable evidence:
- V3 architecture verifier reports zero `WRITE_CONTRACT_MODEL` violations.
- V3 architecture verifier reports zero `SECURITY_CONTEXT_MODEL` violations.
- Repository scan reports zero `AllianceContext->alliance()` / `AllianceContext->player()` call sites.

Failures fixed:
- 56 Eloquent-bearing write contracts.
- 1 Eloquent-bearing security context.
- Stale controller calls to removed request-context live-model methods.

Blockers: none.
Safe to proceed: yes.
