# Event and integration-event reference

Status: Current

Do not conflate three different concepts:

1. **Operations Event** — scheduled game activity owned by `Operations/Events`;
2. **internal durable/outbox event** — implementation message representing a persisted transition;
3. **public webhook event** — externally supported Platform Integration contract.

Internal messages are not automatically public API contracts.

## Current public webhook catalogue

Source: `app/Contexts/Platform/Integrations/Contracts/WebhookEventCatalog.php`

| Event | Scope | Required payload fields | Trigger |
| --- | --- | --- | --- |
| `content.published` | Alliance | `content_item_id`, `revision_number` | Alliance knowledge or an announcement is published. |
| `event.created` | Alliance | `scope`, `target_id` | An Operations Event is created. |
| `event.updated` | Alliance | `scope`, `target_id` | An Operations Event changes. |
| `event.cancelled` | Alliance | `scope`, `target_id` | An Operations Event is cancelled. |
| `member.updated` | Alliance | `member_id`, `player_id`, `change` | Membership status or rank changes. |
| `member.left` | Alliance | `member_id`, `player_id`, `source` | A member or roster entry is marked as having left. |
| `recruitment.candidate.stage_changed` | Alliance | `candidate_id`, `from_stage`, `to_stage` | A candidate moves through the recruitment pipeline. |
| `recruitment.candidate.joined` | Alliance | `candidate_id`, `membership_invitation_id` | An accepted candidate joins the Alliance. |
| `gift_code.created` | Global | `version`, `gift_code_id`, `code`, `status`, `status_revision` | A normalized Gift Code is first added. |
| `gift_code.provenance_added` | Global | `version`, `gift_code_id`, `code`, `source_type`, `status_revision` | A later observation appends source evidence. |
| `gift_code.status_changed` | Global | `version`, `gift_code_id`, `previous_status`, `status`, `status_revision` | Canonical evidence/moderation changes global trust. |
| `gift_code.expiry_changed` | Global | `version`, `gift_code_id`, `status_revision`, `expires_revision` | Qualified evidence or moderation changes canonical expiry. |
| `broadcast.schedule.updated` | Alliance | `schedule_id`, `content_item_id`, `timezone`, `weekdays`, `local_time`, `next_run_at` | A recurring broadcast rule is saved. |
| `broadcast.schedule.cancelled` | Alliance | `schedule_id`, `content_item_id`, `reason` | A recurring broadcast rule is cancelled or deactivated. |
| `broadcast.run.queued` | Alliance | `broadcast_run_id`, `content_item_id`, `recipient_count`, `delivery_count` | A one-off or recurring run is materialized. |
| `broadcast.delivery.succeeded` | Alliance | `broadcast_run_id`, `content_item_id`, `channel`, `status`, `attempt_count` | An external provider acknowledges a delivery. |
| `broadcast.delivery.failed` | Alliance | `broadcast_run_id`, `content_item_id`, `channel`, `status`, `attempt_count`, `retryable` | An external delivery attempt fails. |

Webhook selectors also accept `*`, meaning every current and future event in this public catalogue. A wildcard is stored as the only selector so subscription intent stays unambiguous.

The envelope carries `schema_version`, `id`, `event`, `occurred_at`, `alliance_id`, and `data`. Version `1.0` is published as [JSON Schema](api/webhook-envelope.schema.json). Delivery bodies are capped at 256 KiB, signed with `X-Kingshot-Signature`, idempotent per subscription and source message, and retried with bounded backoff. Internal messages that are not listed above are never fanned out, including to wildcard subscriptions.

Alliance events require an Alliance-scoped source message. Global Gift Code events have no source Alliance and are independently delivered to each active matching subscription using that subscription's Alliance in the envelope. Runtime fanout fails closed when an allowlisted event is missing required fields or has the wrong scope.

Managers can send a targeted `integration.test` envelope to one active subscription. It uses the production signing and delivery path but is not a selectable catalogue event and is never fanned out to other subscriptions. An exhausted delivery may be manually re-queued only while its original payload remains available and its subscription is active; the original delivery identity and cumulative attempt count are preserved.

Signing-secret rotation takes effect immediately, displays the replacement once, and invalidates the previous secret for future deliveries. Existing immutable delivery bodies and their historical signatures remain inspectable.

`alliance.created` is not a public selector because no Alliance subscription can exist before that transition. Membership contracts use the stable `member.updated` and `member.left` vocabulary rather than exposing owner-specific internal event names.

## King Perks transition vocabulary

King Perks planning uses transition concepts including plan creation/publication, appointment assignment/reassignment/confirmation/completion/no-show and skill planning/scheduling/activation. The owning persisted state remains Operations; messages represent transitions and do not become a second state store.

When adding an externally supported webhook event, update the code catalogue, API/integration documentation, security/retry expectations and this reference together.
