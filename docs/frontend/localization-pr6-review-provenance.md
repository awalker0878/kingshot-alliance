# PR 6 — localization review provenance (draft)

This workflow is a **record-integrity checker**, not a native-language review certificate. No native reviewers are authorized solely because this PR exists, and an AI/English-source-risk classification cannot be converted into a human translation approval.

## Attestable review records

Each approved JSON file under resources/data/localization/reviews binds one catalogue (client or server), domain, source key, locale, reviewer GitHub login, PR number, PR review ID, reviewed commit SHA, exact English-source SHA-256 and translation SHA-256. The approved record has version=1, decision=approved and scope=native-translation. The filename is SHA-256 of catalogue:domain:key:locale with .json suffix. The reviewBindingSha256 is computed by the exported binding function in scripts/check-localization-review-provenance.mjs. No invented identities, review numbers or signed records may be added.

A qualified native reviewer must use the **real GitHub PR Approve review action**, on the specified commit, and include the following line (with the actual digest):

Approval-Record-SHA256: <reviewBindingSha256>

The GitHub API validates state=APPROVED, GitHub login, reviewed head and review-body digest. The workflow also re-exports all effective locale messages, server JSON and positional account PHP messages. A changed source or translated string invalidates its prior review. Review decisions in chat, issue comments, spreadsheets and AI output are not approved GitHub reviews.

## Mandatory governance still missing

The provenance job **cannot prove all Tier 1 translations are reviewed** until a product/security-approved list of required Tier 1 message IDs and per-locale coverage is frozen and included in a fail-closed coverage gate. The 511/613 candidate counts are exploratory, not approved scope. An empty registry means zero verified approvals, not green linguistic quality.

The CODEOWNERS patterns here designate the existing repository governance custodian only. They do NOT represent native proficiency. Recruit qualified 16-locale reviewers in issue #182, add actual qualified GitHub handles to the appropriate patterns, and configure target branch protection/rulesets so code-owner reviews and the provenance workflow are required. GitHub CODEOWNERS without a protected-branch rule is notification, not enforcement. That administrative prerequisite must be verified before accepting native sign-offs.

## Acceptance

- [ ] Negative tests for forged review/invalid actor, wrong locale, stale text, and missing approval
- [ ] Final-head CI confirms provenance checker and zero native approvals until real sign-offs exist
- [ ] Product/security owner adjudicates Tier 1 scope; CI requires coverage before native high-risk wording can ship
- [ ] Qualified native reviewers recruited, permissioned, and assigned in CODEOWNERS
- [ ] Target branch is protected with required code-owner reviews and status checks
- [ ] Real native-review round-trip validated independently

No runtime string, PHP locale parser, cookie, localStorage, or PR 5B behavior changes are in this PR.
