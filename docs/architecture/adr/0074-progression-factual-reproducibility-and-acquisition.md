# ADR-0074: Progression factual reproducibility and live acquisition evidence

Status: Accepted

## Context

Progression Source Refresh regenerates the reviewed release from its declared sources, validates the corpus and compares it to the checked-in release. The final byte comparison failed at 027d880b even though all factual artifacts were identical: 115 raw HTML acquisition hashes changed. The source lock also contains eleven structured API source hashes and one commit-pinned GitHub source hash. Existing official/KR semantic snapshots already distinguish factual table content from volatile page markup.

Automatically replacing the checked-in source lock would incorrectly accept fresh acquisition evidence as the old reviewed cutoff. Ignoring all source-lock changes would lose source identity, coverage, pinned bytes and policy validation. Neither is an acceptable reproducibility contract.

## Decision

GameWorld Progression retains source tooling ownership. The read-only workflow first validates the checked-in corpus, runs comparison regression tests, regenerates a candidate and validates that candidate. The comparison reads its baseline directly from Git HEAD, so generation cannot rewrite its own expected output.

The release inventory must match exactly. Every file except source-lock.json must match byte for byte, including factual datasets, release policy and existing semantic snapshots. The source lock must have valid unique acquisition records and hashes, with exactly the same top-level metadata, source order, identities, URLs, datasets, kinds, coverage facts and upstream commit declarations. Changed structured API or commit-pinned hashes reject even if their normalized output is identical.

Only SHA-256 fields for existing, unpinned `kingshotdata` HTTPS HTML acquisitions on the exact `kingshotdata.com` origin may differ. Their existing kinds must be alliance_tech, academy_research, category_hub or factual_detail_page. Unknown kinds/origins, URL credentials/ports/query/fragment changes and changed source metadata do not inherit this exception. Duplicate JSON fields, missing/new files or source rows and generated symbolic links reject.

Permitted acquisition changes produce a separate timestamped report identifying the repository head, source URL/kind, old hash and newly observed hash. The candidate and report are uploaded together as review evidence. The checked-in release, source-lock cutoff and runtime factual authority are never changed by this workflow. Passing this comparison establishes unchanged factual output and pinned inputs; it does not publish a new release or claim that live website bytes are immutable.

## Alternatives and operational consequences

Exact raw HTML byte equality conflates page chrome and delivery changes with factual drift. Comparing only selected semantic fields could miss an unanticipated factual artifact change. This decision keeps every factual artifact exact while narrowly classifying acquisition-byte changes through the reviewed source inventory. Actual upstream fact, coverage, identity or policy changes remain failing review work and require the normal release process.

Generation still depends on accessible declared sources and may fail when those sources change. Such failures remain visible. There is no network fallback, suppression of validation, automatic new baseline or write token added to the workflow.

## Verification

Ten local cases cover exact output, allowed HTML acquisition changes, changed facts/release policy, pinned hashes, source order/inventory/metadata, invalid hashes/duplicate JSON, optional Academy dataset identity, real Git baseline preservation and generated symlink rejection. Replaying the failed hosted diff confirms all 115 changed HTML hashes can be explained while all other artifacts and twelve structured/pinned hashes stay exact. The actual hosted source refresh must also pass with the new comparison.
