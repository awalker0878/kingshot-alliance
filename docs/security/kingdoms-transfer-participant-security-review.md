# KINGDOMS-002 Slice B security review

**Scope:** Slice B / `K2-P2` participant direction and destination  
**Status:** Candidate review evidence

## Security boundary

Transfer participants are Alliance-owned tenant data. Global Kingdom and KingdomPlayer references are neutral identity only and never authorize access to participant intent, membership linkage, manager notes, or destinations.

## Findings and controls

### Cross-tenant object ID tampering

**Risk:** submitted plan, participant, roster, or membership IDs could be used to read or mutate another Alliance's transfer data.

**Control:** every mutation starts from active Alliance context and re-resolves the plan/participant under `alliance_id`; roster and membership references are separately checked against the same Alliance.

### Display-name identity collision

**Risk:** two game players can share a display name and an incoming transfer row could be merged into the wrong neutral identity.

**Control:** display name alone never resolves `KingdomPlayer`. Neutral incoming identity is resolved only from an explicit source Kingdom plus stable game-player ID.

### Identity switching during edit

**Risk:** editing an existing row could silently repurpose historical intent from one person to another.

**Control:** roster identity cannot change in place; known incoming source/stable/resolved identity cannot be replaced; switching incoming ↔ roster-bound requires withdraw + recreate.

### Destination/identity conflation

**Risk:** setting an outgoing destination could incorrectly mutate the global player's Kingdom.

**Control:** source/destination are participant planning references only. Slice B never updates `KingdomPlayer.kingdom_id`.

### Private manager notes

**Risk:** transfer coordination notes could leak to ordinary members or durable integration payloads.

**Control:** notes are returned only on the management surface under `kingdoms.manage` and are excluded from audit/outbox metadata.

### Privilege escalation

**Risk:** ordinary members could mutate transfer intent or a future coordinator concept could bypass RBAC.

**Control:** member reads require `alliance.view`; participant mutations require `kingdoms.manage` plus recent password confirmation. Slice B contains no coordinator authorization path.

### Stale plan context

**Risk:** transfer intent could continue mutating after the Alliance changes Kingdom.

**Control:** participant create/update/withdraw fails closed when current Alliance Kingdom differs from captured plan home Kingdom.

### Duplicate active intent

**Risk:** retries or concurrent manager actions could create duplicate active planning rows.

**Control:** application checks plus PostgreSQL partial unique indexes prevent duplicate active rows for the same roster entry or resolved incoming neutral player.

### External webhook exposure

**Risk:** internal transfer events could become an undocumented external contract through wildcard webhook subscriptions.

**Control:** the accepted `QueueWebhookDeliveries` boundary rejects all `kingdoms.*` events from external fan-out.

## Deferred risks

Groups/coordinators, readiness/blockers, completion/roster handoff, cross-alliance transfer sharing, automated ingestion, marketplace/public advertising, and public API/webhook contracts require later-slice or separate-increment security review before implementation.
