# Platform — Integrations

Status: Current — Architecture V3

Implementation target: `app/Contexts/Platform/Integrations`

Integrations owns external integration administration: scoped API credentials, webhook subscriptions and external delivery coordination.

## API credentials

Credentials are scoped and revocable. API scope is not equivalent to Platform Administrator or Player game authority.

Bot-facing reads are composed in `app/ReadModels/BotCommands` and exposed through the existing credential middleware. Platform/Integrations owns credential verification and usage recording; the read model composes bounded projections from Alliance, Event, Gift Code, Content, Kingdom, and Recruitment owners.

Discord/Telegram adapters are transport clients. They may verify provider requests and format responses, but they must not own a second copy of application business rules. The command API is deliberately read-only and never accepts provider bot tokens.

## Webhooks

Webhook subscriptions use an explicit public event catalogue. Internal outbox/domain transition messages are not automatically public webhook contracts.

Webhook delivery must be signed, retryable/idempotent and operationally observable. Network egress must enforce SSRF protections against metadata/private/management targets.

## Ownership

The source context owns the business fact. Platform/Integrations owns the external credential/subscription/delivery contract and does not mutate the source aggregate to provide integration behavior.