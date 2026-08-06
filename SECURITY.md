# Security Policy

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability. Use the repository's private GitHub Security Advisory reporting flow.

Include:

- affected release or commit
- reproduction steps
- impact and realistic attack conditions
- request IDs or sanitized logs
- suggested mitigation, when known

Do not include production credentials, personal information, session cookies, API tokens, or unredacted alliance data.

## Supported versions

Until the first production release, only the latest commit on `main` is supported. After launch, support windows will be published in the release policy.

## Baseline response

1. Triage severity and exposure.
2. Preserve evidence and establish an incident channel.
3. Contain affected access or functionality.
4. Develop and validate a fix.
5. Rotate exposed secrets.
6. Notify affected parties where required.
7. Publish a sanitized advisory and lessons learned.
