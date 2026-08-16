# API and webhook integrations

Status: Current  
Context: Platform  
Implementation: `app/Contexts/Platform/Integrations`

Platform owns external integration administration: scoped API credentials and webhook subscriptions/delivery coordination.

## API credentials

Credentials are scoped and revocable. Current read scopes exposed by `/v1` include `alliance:read`, `events:read` and `contributions:read`. A credential scope is not equivalent to a Platform Administrator or Player permission.

## Webhooks

Webhook subscriptions use an explicit public event catalogue. Current public events include `alliance.created` and `member.joined` plus supported wildcard selection. Internal outbox/domain transition messages are not automatically public webhook contracts.

Webhook delivery must be signed, retryable/idempotent and operationally observable. Network egress must enforce SSRF protections against metadata/private/management targets; application URL validation alone is not sufficient.

## Ownership

The source context owns the business fact. Platform Integrations owns the external credential/subscription/delivery contract.