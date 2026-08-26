# Event Command readiness and closeout reference

Status: Current

This reference describes the server-built Event Command projection exposed on the normal Event Management read route. It is a read contract only; there are no Event Command mutation endpoints.

## Route

```text
GET /events/{event}/manage?occurrence={occurrenceId}
```

The route already uses the authenticated/verified Event Management boundary. `{event}` is authorized through `Operations/Events`. The optional `occurrence` selector is accepted only when that occurrence belongs to the authorized Event.

No routes such as `mark-ready`, `complete-closeout`, `fix-readiness` or Event Command retry endpoints exist.

## Inertia property

`Operations/Events/Manage` receives an `eventCommand` property with this shape:

```text
{
  eventId: string,
  selectedOccurrenceId: string|null,
  occurrences: Array<{
    id: string,
    startsAt: ISO-8601 string,
    endsAt: ISO-8601 string,
    status: string,
    selected: boolean
  }>,
  state: "planning"|"needs_attention"|"ready"|"active"|"closeout_required"|"complete"|null,
  eventStatus: string,
  occurrenceStatus: string|null,
  startsAt: ISO-8601 string|null,
  endsAt: ISO-8601 string|null,
  timezone: string,
  blockerCount: number,
  warningCount: number,
  sections: EventCommandSection[]
}
```

`state` is null when no occurrence can be selected or cancellation is the governing Event truth.

## Section/item contract

```text
EventCommandSection {
  key: string,
  labelKey: string,
  phase: "readiness"|"closeout",
  items: EventCommandItem[]
}

EventCommandItem {
  code: string,
  phase: "readiness"|"closeout",
  status: "complete"|"needs_attention"|"warning"|"unknown"|"not_applicable",
  severity: "blocking"|"warning"|"informational",
  owner: string,
  classification: "operational_fact"|"alliance_strategy"|"evidence"|"derived",
  count: number|null,
  messageKey: string,
  messageParameters: Record<string,string|number|null>,
  source: Record<string,mixed>|null,
  handoff: { href: string, labelKey: string }|null
}
```

All visible prose is localized client-side from `messageKey` plus bounded scalar parameters. Backend owner errors are not rendered as raw exception text.

## Canonical owner keys

- `operations.events`
- `operations.participation`
- `operations.polls`
- `operations.rosters`
- `operations.battle_plans`
- `operations.rallies`
- `operations.results`
- `operations.territory_planning`
- `operations.reminders`
- `alliance.content`
- `communications.delivery`
- `intelligence.evidence`
- `readmodels.event_analysis`

## Status/count rules

`blockerCount` counts items with severity `blocking` whose status is `needs_attention` or `unknown`.

`warningCount` counts items with severity `warning` whose status is `warning` or `unknown`.

Counts are response values only and are never persisted.

Missing owner state must be represented as `unknown` when an applicable requirement cannot be established. `not_applicable` is used only when capability/lifecycle semantics establish that the dimension does not apply.

## Occurrence selection precedence

With no explicit selector, Event Command chooses:

1. a currently active non-cancelled occurrence;
2. the most recent ended non-cancelled occurrence whose closeout still has blockers;
3. the next upcoming non-cancelled occurrence;
4. the most recent occurrence;
5. none.

An explicit selector takes precedence only after it is constrained by `event_id`.

## Handoff anchors

Same-page Event Management handoffs use stable anchors including:

- `#schedule`
- `#participants`
- `#polls`
- `#rosters`
- `#battle-plan`
- `#rallies`
- `#results`
- `#territory-positioning`
- `#reminders`

External owner workflows use their normal authorized route, for example Alliance Content, Screenshot Intake and Debrief. Following a handoff does not carry authorization or mutation authority from Event Command.

## Results correction capability

`Operations/Results` currently exposes no explicit unresolved-correction aggregate/workflow. The projection therefore reports correction-state support as not applicable/informational instead of deriving corrections from timestamps or differences. A future correction workflow must be owner-defined before Event Command can consume it.
