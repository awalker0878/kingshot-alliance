# ADR-0077 — Event phase/poll catalogues and vote aggregates

Status: Accepted

## Context

Selecting one occurrence bounds management's occurrence multiplier but does not bound its phases, polls, options or retained votes. The member projection has the same growing collections. Poll results previously hydrated every vote and issued a separate selected-choice query for each poll.

## Decision

Events owns a 25-row phase catalogue, ordered by sort order, start time with PostgreSQL nulls last, and identity. Polls owns a separate 25-row poll catalogue ordered by creation identity. Each query fetches one sentinel, reports an exact current total, and signs the initial ULID frontier and continuation position with the shared encrypted cursor codec. Actor and occurrence bind both cursors; poll visibility also binds manager/member mode. Member catalogues contain open and closed polls only. The read adapters recheck current occurrence authority before either catalogue. A cursor never grants access.

The captured frontier excludes higher-ID insertions until a fresh traversal. These are finite, currently authorized traversals, not immutable snapshots: subsequent edits, lifecycle changes and deletions remain current. Phase continuation explicitly handles tied dates and the null tail. Existing retained rows remain reachable through complete navigation.

Polls materializes options only for the displayed 25 polls. Its existing 50-option owner contract caps that set at 1,250; one sentinel and a per-poll check reject corrupt excess explicitly. SQL groups all retained votes by these bounded options, returning exact totals and the current eligible Governor's selected choices without loading vote models. Open member results remain hidden, closed results remain available, and manager results remain exact. Voting windows and mutation authority stay with the existing Poll owner.

EventCalendar and EventManagement compose these owner facts read-only. Independent phase and poll controls retain unrelated cursor positions, occurrence selection, scroll and manager form drafts. Member selected-choice state is replaced by the current page's server facts, so navigating through history does not accumulate prior vote maps. Vote buttons expose their selected state with aria-pressed.

## Verification and limits

PollProjectionPagesTest covers complete phase ordering with ties/nulls and new insertions, manager/member poll visibility, actor/occurrence/visibility cursor scope, 1,501 exact votes without vote hydration, hidden open results, explicit corrupt-option rejection and current authority on a later HTTP page. Browser coverage traverses manager phase/poll pages independently while preserving drafts, reaches the final draft poll, and saves a member vote before leaving and revisiting its page; private drafts remain absent from member results. Hosted execution and complete containing gates remain required.

This decision addresses phase/poll/vote collections within HARD-134. Participation, roster/Rally members and selectors, result/reminder lists and EventPlayerIntelligence history remain separate open audit work. Bear Hunt Debrief result projection remains HARD-135.
