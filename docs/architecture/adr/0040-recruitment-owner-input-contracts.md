# ADR-0040: Recruitment owner input contracts

Status: Accepted

## Context

Recruitment configuration forms already had several size limits, but direct mutation owners enforced only presence or partial position/selection checks. Oversized configuration and candidate identity fields could fail in storage or persist unbounded text. Short/long application answers and repeated multi-select values were not bounded. Several management forms also lacked visible validation feedback.

## Decision

RecruitmentInput owns the current configuration/intake limit map and scalar normalization. Configuration, question create/update, decision-template, onboarding-item, application-invite and public-submission actions validate before transactions or effects. Required values remain required; optional blank text becomes null. Invalid positions, retention periods and invite lifetimes return field validation instead of argument exceptions. Invite email is normalized and validated by the same email contract as submission.

| Input | Limit |
| --- | --- |
| Application title / candidate name / contact handle | 160 characters |
| Introduction / onboarding description | 5,000 characters |
| Question prompt / short answer | 240 characters |
| Question help text | 2,000 characters |
| Question options | 30 entries, 160 characters each |
| Decision template name / source | 120 characters |
| Decision subject | 200 characters |
| Decision body / long answer | 10,000 characters |
| Onboarding item name | 160 characters |
| Email | Existing address validation and 320-character outer bound |
| Position | 0–65,535 |
| Unsuccessful retention | 1–3,650 days |
| Application invite lifetime | 1–720 hours |

Question options must be a list of strings; valid values are trimmed and deduplicated, preserving existing blank-option cleanup. Multi-select answers must contain only available choices, each once, with no more than 30 entries. Text answers are trimmed, bounded by their question type and stored consistently for direct and HTTP submission. Existing required-checkbox and optional-empty answer semantics remain.

The management and public pages receive the same limit map. HTTP validation references it, text controls use its lengths, and management forms and individual question edits expose validation errors without discarding the draft. Current permission/context checks and recent authentication remain in place. Public-request regression tests advance time between attempts to respect the existing three-per-minute throttle.

## Consequences

Malformed or oversized inputs produce useful validation feedback before owner records, audit events or outbox messages change. Fifteen configuration/page cases and ten answer cases exercise direct/HTTP entry points, exact Unicode boundaries, rollback-free rejection, list/count/value rules and required/optional semantics. The earlier note/reason HTTP cases also supply the recent-authentication state their protected routes require. Collection cardinality and catalogue pagination remain separately tracked under HARD-088; this decision does not silently truncate a required questionnaire.

HARD-090 also initializes edit state for newly returned questions while preserving existing drafts. Two browser cases verify visible create/update errors, retained values and creating then editing a question without reloading on desktop/mobile.
