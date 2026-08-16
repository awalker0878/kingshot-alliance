# Event and integration-event reference

Status: Current

Do not conflate three different concepts:

1. **Operations Event** — scheduled game activity owned by `Operations/EventCore`;
2. **internal durable/outbox event** — implementation message representing a persisted transition;
3. **public webhook event** — externally supported Platform Integration contract.

Internal messages are not automatically public API contracts.

## Current public webhook catalogue

Source: `app/Contexts/Platform/Integrations/Contracts/WebhookEventCatalog.php`

- `alliance.created`
- `member.joined`

Webhook selectors also accept `*` where the integration contract allows it.

## King Perks transition vocabulary

King Perks planning uses transition concepts including plan creation/publication, appointment assignment/reassignment/confirmation/completion/no-show and skill planning/scheduling/activation. The owning persisted state remains Operations; messages represent transitions and do not become a second state store.

When adding an externally supported webhook event, update the code catalogue, API/integration documentation, security/retry expectations and this reference together.