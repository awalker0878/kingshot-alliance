# Platform — Integrations

Status: Current — Architecture V3

Implementation target: `app/Contexts/Platform/Integrations`

Integrations owns external integration administration: scoped API credentials, webhook subscriptions and external delivery coordination.

## API credentials

Credentials are scoped and revocable. API scope is not equivalent to Platform Administrator or Player game authority.

## Webhooks

Webhook subscriptions use an explicit public event catalogue. Internal outbox/domain transition messages are not automatically public webhook contracts.

Webhook delivery must be signed, retryable/idempotent and operationally observable. Network egress must enforce SSRF protections against metadata/private/management targets.

## Ownership

The source context owns the business fact. Platform/Integrations owns the external credential/subscription/delivery contract and does not mutate the source aggregate to provide integration behavior.