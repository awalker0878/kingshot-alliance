# CA-P10 — Architecture Enforcement

Status: **PASS**

## Enforcement hardened

The V3 architecture verifier now enforces repository structure and the authority rules established by CA-P0 through CA-P9 rather than relying on a capability registry.

New permanent checks include:

- Business contexts may not import another context's Eloquent model.
- The authenticated `Accounts\\Identity\\Models\\User` model is permitted only at an HTTP/authentication boundary.
- Public write Actions/Commands/Jobs/Listeners/Subscribers/Workflows may neither accept nor return Eloquent models.
- Write Actions may not interpret another context's permission vocabulary; they must invoke owner-owned semantic authorization APIs.
- `*MutationAuthority*` classes/interfaces/traits/enums may not be reintroduced.
- Workflows may not own Models, Repositories or migrations and may not interpret permission enums.
- Removed Intelligence EventAnalysis/Roster bridge namespaces may not be reintroduced.
- Deleted live-model `AllianceContext::alliance()` / `AllianceContext::player()` accessors may not be restored.

Existing enforcement remains in place for:

- exactly seven business contexts;
- no context-root technical-layer directories;
- no Context -> Workflow/ReadModel dependencies;
- no workflow persistence ownership;
- no model-bearing request/security contexts;
- no cross-context Eloquent relationships;
- no transaction/locking inside authorization readers;
- no HTTP-owned transactions/direct persistence;
- no ReadModel writes;
- Communications remaining delivery-only;
- the exact intended Workflow set.

## Verification

- `php -l tests/v3/Architecture/verify.php`: PASS
- `php tests/v3/Architecture/verify.php`: PASS
- Foreign permission imports in write Actions: 0
- `MutationAuthority` symbols: 0
- Removed bridge namespace references: 0

Known blockers: **NONE**

Safe to proceed: **YES**
