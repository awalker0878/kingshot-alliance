# Event and integration-event reference

Status: Current

Do not conflate three different concepts:

1. **Operations Event** — scheduled game activity owned by `Operations/Events`;
2. **internal durable/outbox event** — implementation message representing a persisted transition;
3. **public webhook event** — externally supported Platform Integration contract.

Internal messages are not automatically public API contracts.

## Current public webhook catalogue

Source: `app/Contexts/Platform/Integrations/Contracts/WebhookEventCatalog.php`

| Event | Stable payload fields | Trigger |
| --- | --- | --- |
| `content.published` | `content_item_id`, `revision_number`, `scheduled_for` | Alliance knowledge or an announcement is published. |
| `event.created` | `scope`, `target_id`, `event_type_scope_id`, `occurrence_count`, `published`, `actor_player_id` | An Operations Event is created. |
| `event.updated` | `scope`, `target_id`, `before`, `schedule_changed`, `actor_player_id` | An Operations Event changes. |
| `event.cancelled` | `scope`, `target_id`, `actor_player_id` | An Operations Event is cancelled. |
| `membership.rank_changed` | `membership_id`, `player_id`, `previous_rank`, `rank` | A member's Alliance rank changes. |
| `membership.roster_entry_left` | `roster_entry_id`, `player_id` | A Governor is marked as having left the roster. |
| `recruitment.candidate.stage_changed` | `candidate_id`, `from_stage`, `to_stage` | A candidate moves through the recruitment pipeline. |
| `recruitment.candidate.joined` | `candidate_id`, `membership_invitation_id` | An accepted candidate joins the Alliance. |

Webhook selectors also accept `*`, meaning every current and future event in this public catalogue. A wildcard is stored as the only selector so subscription intent stays unambiguous.

The envelope also carries `id`, `event`, `occurred_at`, `alliance_id`, and `data`. Delivery bodies are capped at 256 KiB, signed with `X-Kingshot-Signature`, idempotent per subscription and source message, and retried with bounded backoff. Internal messages that are not listed above are never fanned out, including to wildcard subscriptions.

Managers can send a targeted `integration.test` envelope to one active subscription. It uses the production signing and delivery path but is not a selectable catalogue event and is never fanned out to other subscriptions. An exhausted delivery may be manually re-queued only while its original payload remains available and its subscription is active; the original delivery identity and cumulative attempt count are preserved.

`alliance.created` is not a public selector because no Alliance subscription can exist before that transition. The former `member.joined` selector was removed because the domain never emitted that event; recruitment and roster transitions above are the supported contracts.

## King Perks transition vocabulary

King Perks planning uses transition concepts including plan creation/publication, appointment assignment/reassignment/confirmation/completion/no-show and skill planning/scheduling/activation. The owning persisted state remains Operations; messages represent transitions and do not become a second state store.

When adding an externally supported webhook event, update the code catalogue, API/integration documentation, security/retry expectations and this reference together.
