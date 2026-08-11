# Shared architecture and product glossary

[← Product and program documentation](README.md)

**Document type:** Shared terminology reference  
**Status:** Current  
**Phase owner:** `DCP-P6`

Use this glossary for terms whose ambiguity can change architecture, tenancy, authorization, integration, evidence, or product-status meaning. Domain-specific vocabulary remains owned by the applicable domain contract.

## Core identity and tenancy terms

### User

A global Identity-owned account. A User is not automatically a member of any Alliance and does not receive Alliance permissions merely by authenticating.

### Alliance

The platform tenant aggregate owned by the Alliances domain. Alliance-scoped business data is isolated by this tenant identity.

Do not use `Alliance` to mean a Kingshot game-side alliance when the distinction matters; use `KingdomAlliance` for the neutral game reference.

### Active Alliance

The explicit request/workflow tenant context selected for normal Alliance-scoped behavior. It must be validated against Memberships-owned active membership before tenant data is accessed.

### Membership

Memberships-owned relationship between a global User and an Alliance, with lifecycle such as active/suspended/left/removed. Membership is distinct from identity and distinct from authorization roles.

### Role / permission

Authorization-owned Alliance authority. Permission checks use the stable permission vocabulary and applicable effective-rank/Owner safety semantics rather than inferring authority from display role names.

### Platform administrator

A Platform-owned cross-tenant administrative grant. It is not an Alliance role and must not be represented as one.

## Kingdoms terms

### Kingdom

A Kingdoms-owned neutral game-world reference. An Alliance may reference one canonical Kingdom through `kingdom_id`.

### KingdomPlayer

A neutral Kingshot player reference owned by Kingdoms. Sharing the neutral reference never grants access to another Alliance's tenant-owned roster/history/notes/intelligence.

### KingdomAlliance

A neutral game-side Alliance reference owned by Kingdoms. This term is deliberately different from the platform tenant `Alliance`.

### TrackedKingdomAlliance

Alliance-owned tracking/observation relationship to a neutral `KingdomAlliance`. Tracking state is tenant-owned even though the referenced game-side Alliance identity is neutral/shared.

### Neutral reference

Global/reference identity that can be shared without sharing tenant-owned observations, notes, history, plans, contacts, or authorization.

### Stable game identifier

An identifier accepted as an automatic neutral identity key only within its documented game scope (for example within a Kingdom). Names, tags, and handles are not automatic merge keys.

## Events, rallies, notifications, and contributions

### Event

Events-owned scheduled activity and its recurrence/occurrence/registration/attendance lifecycle.

### Rally

Rallies-owned guidance/formation/group/assignment/participation state associated with Event occurrence context. Rally responsibility does not grant authorization.

### Reminder delivery

Notifications-owned durable state coordinating an Event reminder. Events owns the source occurrence/registration facts; Notifications owns reminder materialization/delivery state.

### Scheduled contribution report request

Notifications-owned due-time coordination for a Contributions-owned report schedule/run contract. Notifications does not own report semantics.

### Contribution

Contributions-owned fact/calculation/assessment/reporting state. Event attendance remains Events-owned even when Contributions reconciles from it.

## Asynchronous and integration terms

### Transactional outbox

Platform-owned durable asynchronous publication infrastructure. Feature domains own the business transition/payload semantics that justify an outbox message.

### Internal domain/outbox event

A durable/internal event used for first-party coordination. Its existence does **not** automatically create an external webhook contract.

### Externally eligible webhook event

An event explicitly approved by the Integrations contract for outbound delivery. Integrations owns subscription/signature/delivery/retry behavior; producer domains own event-specific business payload meaning.

### API credential

An Integrations-owned Alliance-bound machine credential with fixed approved read scopes. It is not a user session and does not imply unapproved domain/API access.

### Idempotency

The property that repeating an operation with the same owning identity/key/state contract does not create an unintended second effect. P5 requires discoverable executable evidence before a critical workflow is described as idempotent.

## Documentation and evidence terms

### Living contract

Current documentation that describes how the implemented system works now. Domain READMEs and current security/operations/interfaces/testing profiles are living contracts/maps.

### Historical evidence

An immutable or append-hardened record proving a past decision, validation, or acceptance at a recorded revision. Historical evidence is not automatically current runtime truth.

### ADR

Architecture Decision Record. It preserves a durable architecture decision, rationale, consequences, and lifecycle. ADR states are Proposed, Accepted, Superseded, or Rejected.

### Supported contract

An intentional owner-provided surface used across domain boundaries: for example an action, service, query, value object, enum, domain event, explicit reference model contract, or documented adapter.

### Persistence reach-through

A consumer directly treating another domain's persistence internals as its own state/behavior instead of using a supported owning contract. This is an architecture defect unless explicitly accepted by architecture ownership.

## Status and release terms

### Implemented

Current runtime behavior exists in code/tests. This does not by itself mean a named increment or production launch has been accepted/approved.

### Accepted

A repository/product completion gate passed and immutable evidence is retained. `Accepted` is not synonymous with production `Approved`.

### Approved

An accountable scope/decision or external production decision has been explicitly approved. Approval does not imply implementation if the approval is for planned scope.

### Candidate

Implementation/documentation content is complete for a defined gate but protected acceptance is still pending.

### Validated

A defined validation gate passed on the recorded revision. Validation may be narrower than full acceptance.

### Not implemented

No accepted runtime capability exists for the stated behavior.

### Not yet approved

A decision requires accountable/external evidence before proceeding. The repository may be technically capable while production use remains not yet approved.

### Repository-controlled production hardening

Build/test/image/staging/recovery/scan controls demonstrable by repository automation. This is distinct from real-production infrastructure/operator evidence.

### Real production launch

Actual production cutover under deployed ingress/TLS/network/secret/dependency/operator/alert/recovery controls. It remains governed by the production launch approval record rather than by CI alone.

## Maintenance rule

Add or change a shared term only when system-level ambiguity exists. If a term belongs to one domain's model, update the owning domain contract and link it rather than duplicating its full model here.
