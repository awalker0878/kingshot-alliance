# Platform — Integrations

Status: Current — Architecture V3

Implementation target: `app/Contexts/Platform/Integrations`

Integrations owns external integration administration: scoped API credentials, webhook subscriptions and external delivery coordination.

## API credentials

Credentials are scoped and revocable. API scope is not equivalent to Platform Administrator, Alliance manager, or Player game authority.

Bot-facing reads are composed in `app/ReadModels/BotCommands` and exposed through the existing credential middleware. Platform/Integrations owns credential verification and usage recording; the read model composes bounded projections from Alliance, Event, Gift Code, Content, Kingdom, and Recruitment owners.

The canonical global Gift Code read is `GET /api/v1/gift-codes`. Alliance credentials may read only verified active/unexpired entries through opaque cursor pagination; requesting platform review states fails closed. Approved-source ingestion is not an Alliance API capability and uses separately deployed platform-owned adapters/policy.

Discord/Telegram adapters are transport clients. They may verify provider requests and format responses, but they must not own a second copy of application business rules. Read contracts never accept provider bot tokens. Self-service write parity requires a revocable, verified external-actor link and owner-context action; a client-provided Player ID is never an authority source.

Platform/Integrations owns the provider link and machine request receipt. `Workflows/ExternalEventParticipation` owns the multi-context HTTP adapter, resolves the link, and coordinates the call into Operations/Participation. This keeps contexts from depending upward on a workflow. Operations remains the only owner of response, registration, capacity and waitlist semantics.

## Webhooks

Webhook subscriptions use an explicit public event catalogue with required payload fields and Alliance/global scope. Internal outbox/domain transition messages are not automatically public webhook contracts.

Webhook delivery must be signed, retryable/idempotent and operationally observable. Its envelope and public API surface are published as versioned machine-readable contracts and checked against runtime routes/catalogue. Network egress must enforce SSRF protections against metadata/private/management targets.

Gift Code create/provenance/trust/expiry events are global contracts. Their payload data is versioned, carries `status_revision`, and carries `expires_revision` for expiry changes so a return to an earlier status value remains a distinct deliverable transition.

## Ownership

The source context owns the business fact. Platform/Integrations owns the external credential/subscription/delivery contract and does not mutate the source aggregate to provide integration behavior.
