# ADR-0073: Bounded Poll owner inputs and stable voted choices

Status: Accepted

## Context

The Event HTTP adapter limited options, selected choices and string lengths, while the Poll owner Actions accepted raw arrays and repeated normalization inside their transactions. Direct calls could persist more than 50 options, expand arbitrary nested option metadata, bypass field/settings limits or allocate a large duplicate selection list before checking its distinct size. Existing votes prevented option replacement but did not prevent changing poll type or maximum choices.

## Decision

Operations Polls remains the authoritative writer. Before acquiring owner locks, SaveEventPoll validates key/question/translation-key lengths, a list of at most 50 options, option labels/values and the supported deadline-reminder setting. Option metadata remains a bounded flat map: at most 20 named scalar fields, 64-byte keys and 500-byte string values, with finite numbers and valid UTF-8 strings. Nested structures and unknown option/settings fields fail validation. Deadline reminders are null or 1–10,080 whole minutes; the HTTP adapter explicitly converts its validated integer value before calling the owner.

CastEventPollVote validates a raw list of 1–20 ULID strings before deduplication or transaction admission. Duplicate IDs within that work budget still represent one choice, preserving the existing behavior. The current protected Poll row then enforces its maximum choices and same-poll option ownership.

Once votes exist, option replacement, poll-type changes and maximum-choice changes reject atomically. Closing a poll and other supported lifecycle edits remain possible. Before voting, changing type requires a complete replacement option list, validated under the new type, so choice strings cannot silently become time-vote values.

Owner scope acquisition, current actor authorization, voting windows and audit/outbox transactions are unchanged. Pure input validation does not read tenant facts or disclose existing records. This is one owner contract; HTTP validation remains an adapter that presents errors and parses transport values.

## Alternatives and consequences

Only relying on HTTP leaves internal Actions and future adapters unprotected. Silently truncating options or selected IDs changes the requested poll or vote. Allowing type/maximum changes after votes reinterprets existing choices and makes historical votes violate their current rule. The explicit owner validation and voted-field immutability avoid those outcomes without copying permissions into frontend code or adding a parallel Poll implementation.

Poll history navigation and aggregate read bounds are separate HARD-134 work. This decision bounds one write and its payload; it does not claim that all retained Poll read composition is bounded.

## Verification

Twenty-one direct invalid-input cases establish validation before any SQL query. PostgreSQL cases cover 50 options, 20 selected choices, duplicate normalization, voted-field rejection with complete state equality, valid lifecycle edits, revalidated type changes and late outbox rollback/retry. Existing Event owner scope and concurrency tests remain active. Containing hosted behavior is required before completion.
