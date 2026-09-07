# Kingdom Transfer official-rule source matrix

Status: **Current — verified 2026-09-07**

This matrix is the authority boundary for first-class Kingdom Transfer game rules. Century Games / KingShot Help Center material is authoritative for fixed published rules. Mutable per-Kingdom and per-Governor values still require a current in-game or reviewed Evidence observation; community material cannot satisfy an authoritative eligibility requirement.

| Rule | Product interpretation | Authority | Confidence | Implementation |
| --- | --- | --- | --- | --- |
| Hero Generation compatibility | Source Governor / Kingdom and target Kingdom must be on the same Hero Generation. | [KingShot Help — Kingdom Transfer eligibility](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8561-what-is-the-eligibility-for-kingdom-transfer/) | High | First-class `hero_generation` requirement. |
| Truegold compatibility | Source Governor / Kingdom and target Kingdom must have the same Truegold level. | [KingShot Help — Kingdom Transfer eligibility](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8561-what-is-the-eligibility-for-kingdom-transfer/) | High | First-class `truegold_level` requirement. |
| Character age threshold | Character age relative to the target Kingdom must not exceed the target-specific threshold; published bounds are 90–180 days. | [KingShot Help — Kingdom Transfer eligibility](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8561-what-is-the-eligibility-for-kingdom-transfer/) | High | Target condition stores the observed threshold; evaluator fails closed if it is unknown. |
| Ordinary Kingdom capacity | Total 55: 35 Ordinary Invite + 20 Transfer Opens. | [KingShot Help — Leading & Ordinary Kingdoms](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8559-leading-kingdoms-ordinary-kingdoms/) | High | Fixed rulebook constants plus observed used counts and Alliance reservations. |
| Leading Kingdom capacity | Total 30: 20 Ordinary Invite + 10 Transfer Opens. | [KingShot Help — Leading & Ordinary Kingdoms](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8559-leading-kingdoms-ordinary-kingdoms/) | High | Fixed rulebook constants plus observed used counts and Alliance reservations. |
| Leading Kingdom Special Invites | Leading Kingdoms cannot issue Special Invites. | [KingShot Help — Leading & Ordinary Kingdoms](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8559-leading-kingdoms-ordinary-kingdoms/) | High | Special-Invite allocation requires authoritative Ordinary classification. |
| Special Invite qualification | Special Invites are for Governors above the target Power Cap. | [KingShot Help — Special & Ordinary Invites](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8547-special-invites-ordinary-invites/) | High | Power-cap requirement transitions to Special Invite requirement above cap. |
| Special Invite inventory | Initial / maximum 3; one spent invite restores on the first of each month at UTC 00:00. | [KingShot Help — Special & Ordinary Invites](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8547-special-invites-ordinary-invites/) | High | Rulebook maximum 3; current inventory is observed rather than inferred from an incomplete local history. |
| Transfer Pass requirement | Higher Power requires more Passes; published range is 1–50. No public exact formula is asserted. | [KingShot Help — Transfer Pass calculation](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8552-how-many-transfer-pass-does-one-need-for-a-transfer-how-is-it-calculated/) | High for range/direction; unknown for exact formula | Required Pass count remains an observed game fact and is validated to 1–50. The application must not invent a formula. |
| Transfer cooldown | A second Transfer is permitted only 25 days after the prior Transfer. | [KingShot Help — second Transfer](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8560-can-i-perform-a-second-transfer-if-i-have-sufficient-transfer-passes/) | High | First-class cooldown-remaining observation; any positive remaining days blocks. |
| Character-per-Kingdom limit | A Kingdom can contain up to four characters for one account. | [KingShot Help — character limit](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8564-if-the-target-kingdom-already-has-2-characters-can-i-still-transfer-to-the-kingdom/) | High | Target character-count observation blocks when already at four. |
| Resource consequence | Resources above Storehouse Protection are lost during Transfer. | [KingShot Help — resource loss](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8555-why-do-i-end-up-with-fewer-resources-after-a-transfer/) | High | Explicit pre-flight resource-protection check / warning; not treated as an invented eligibility formula. |

## Evidence and freshness policy

Published constants above may be encoded in `TransferOfficialRulebook`. Values that can change by event, target Kingdom, Governor, or inventory must retain provenance and observation time. Current Power Cap, target classification, Hero Generation, Truegold level, character-age threshold, consumed capacity, Special Invite inventory, Governor Power, Pass counts, cooldown state and character count therefore remain observed facts.

`official_publication`, `in_game`, and reviewed `evidence` sources may satisfy authoritative requirements. Manager notes and community observations may support planning context but cannot make an unknown eligibility requirement pass.

A reviewed screenshot can only commit fields defined by its registered Evidence schema. `transfer-target-kingdom-rules/2` carries target Kingdom, Power Cap, Hero Generation, Truegold level, character-age threshold and an explicitly visible Kingdom classification. It never sets the generic `in_game_rules_verified` gate.

## Non-claims

The application does **not** derive the exact Transfer Pass formula, infer current Special Invite inventory solely from the monthly restoration rule, infer unobserved Kingdom classification, or convert a planning reservation into proof that the in-game slot is still available. Those facts remain fail-closed until authoritative current evidence is available.
