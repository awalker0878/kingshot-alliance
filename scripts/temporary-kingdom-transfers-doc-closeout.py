from pathlib import Path

catalogue = Path('docs/product/capability-catalogue.md')
text = catalogue.read_text()
start = text.index('## Kingdom Transfer Planning product contract')
end = text.index('\n## Bear Hunt Debrief product contract', start)
replacement = '''## Kingdom Transfer Planning product contract

Kingdom Transfer Planning is a delivered `GameWorld/KingdomTransfers` capability, not a generic workflow. It preserves participant/readiness/blocker/completion behavior while adding:

- sourced Transfer Windows and official window-scoped Transfer Groups;
- sourced target Power Cap/classification, Hero Generation, Truegold and character-age thresholds;
- first-class cooldown, target-character-limit, Transfer Pass and Storehouse pre-flight requirements;
- observed Ordinary Invite/Transfer Opens/Special Invite capacity separated from Alliance slot reservations and invitation allocations;
- deterministic fail-closed eligibility with visible provenance/freshness/next actions;
- reviewed Transfer Evidence target-rules v2 for fixture-proven Power/classification/Hero/Truegold/age facts;
- a strict boundary that required Transfer Passes are observed in-game because no safe exact public formula is encoded;
- completion/withdrawal reconciliation of Alliance planning commitments without converting planning intent into game truth;
- terminology reserving **Transfer Group** for the official game concept and **Transfer Cohort** for Alliance planning.

The canonical contract lives in [Kingdom Transfer Planning](kingdom-transfer-planning.md), with current official-rule evidence in the [source matrix](kingdom-transfer-official-rules-source-matrix.md) and reviewed screenshot semantics in [Transfer Evidence](screenshot-intake-transfer-evidence.md).
'''
catalogue.write_text(text[:start] + replacement + text[end:])

ledger = Path('docs/product/capability-delivery-ledger.md')
text = ledger.read_text().replace('Status: Current as of 2026-09-04', 'Status: Current as of 2026-09-07', 1)
old = '| Kingdom Transfer Planning | Complete | [Kingdom Transfer Planning](kingdom-transfer-planning.md) |'
new = '| Kingdom Transfer Planning | Complete | [Kingdom Transfer Planning](kingdom-transfer-planning.md), [official-rule source matrix](kingdom-transfer-official-rules-source-matrix.md), [Transfer Evidence](screenshot-intake-transfer-evidence.md), owner reference/operations docs and KingdomTransfers V3 tests |'
if old not in text:
    raise SystemExit('Kingdom Transfer ledger row not found')
ledger.write_text(text.replace(old, new, 1))
