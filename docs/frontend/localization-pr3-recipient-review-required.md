# Recipient Delivery — native and domain review follow-up (no translation edits)

**Scope:** all **41 differing-value collisions** from the verified pre-PR3 Recipient Delivery overlay, as listed in [the committed baseline-winner inventory](localization-pr3-recipient-collision-winners.md). These values are already the English runtime winners before the PR3 structural refactor. The original French (`fr`), Spanish (`es`) and Arabic (`ar`) domain catalogues contain Recipient Delivery-specific values for these keys; **no translations are changed in PR3**.

**Status:** all 41 are `review_required` for accountable native-language sign-off. This is a *manual content QA follow-up*, **not** an alteration of the committed immutable Tier 1a 511-candidate screen. Structural parity or placeholder parity does not certify semantic equivalence. Missing or unverified sign-off must not be represented as approved.

The CI-generated `recipient-language-review.json` includes the exact English/FR/ES/AR effective strings and checks key coverage and substitution-placeholder parity for all 41. It does **not** assert natural-language meaning equivalence. The table identifies known/suspected priority risks; other 41-key entries still await qualified review.

| Exact English winner key | Status for FR/ES/AR | Triage observation |
|---|---|---|
| `notifications.eyebrow` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.subtitle` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.externalChannels` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.needsAttention` | `review_required` | French 'Questions de livraison' and Arabic 'مسائل التنفيذ' may weaken 'Delivery issues' meaning. |
| `notifications.latestActivity` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.empty` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.deliveryChannels` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.deliverySecurity` | `review_required` | Security claim: encryption, browser non-disclosure, active Governor scope. Require security/product verification and native-language accuracy. |
| `notifications.label` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.webhookUrl` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.botToken` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.chatId` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.saveChannel` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.selectGovernor` | `review_required` | Arabic rendering 'انتقِ مُحكِّماً' is potentially a referee/arbitrator rather than a game Governor. |
| `notifications.reminderRouting` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.statuses.pending` | `review_required` | Semantic state: baseline runtime winner 'Sending' for status key pending. Verify backend meaning and state-machine mapping; needs account-domain approval. |
| `notifications.types.allianceAnnouncement` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.types.eventReminder` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.types.giftCodeExpiring` | `review_required` | Arabic 'رمز الهدية' omits expiring/expiration. |
| `notifications.types.giftCodeAvailable` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.types.giftCodeTrustChanged` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.types.intelligenceChange` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.types.kingPerkReminder` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.types.officerBrief` | `review_required` | French 'Note d ' information' has odd spacing and may miss officer-specific meaning. |
| `notifications.bulk.selected` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.bulk.help` | `review_required` | 50-message limit is a semantic quantity; verify translation preserves cap. |
| `notifications.bulk.operation` | `review_required` | French/Spanish 'en vrac/a granel' may imply retail bulk rather than bulk message operations. |
| `notifications.bulk.markRead` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.bulk.selectVisible` | `review_required` | French 'Sélectionner la couleur montrée' adds a colour not present in the source; Arabic 'حدد العرض' also ambiguous. |
| `notifications.bulk.selectNotification` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.bulk.preview` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.bulk.previewing` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.bulk.previewTitle` | `review_required` | Verify what action is being previewed. |
| `notifications.bulk.previewSummary` | `review_required` | French 'déjà en cours' means already in progress rather than already current. Risk in bulk readiness/blocked summary. |
| `notifications.bulk.confirm` | `review_required` | Bulk changes to inbox must have accurate consent wording. |
| `notifications.bulk.confirmTitle` | `review_required` | Bulk changes to inbox must have accurate consent wording. |
| `notifications.bulk.confirmDescription` | `review_required` | Arabic syntax may obscure action and count. Bulk confirmation semantics require native review. |
| `notifications.bulk.applying` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.bulk.resultTitle` | `review_required` | No documented native-language sign-off; compare French, Spanish and Arabic against existing English runtime winner. |
| `notifications.bulk.resultSummary` | `review_required` | Bulk success/failure/skipped counts require semantic accuracy. |
| `notifications.bulk.failedSelected` | `review_required` | Failure/retry language impacts workflow decisions. |

## Cross-language spot checks, not native approval

- `notifications.eyebrow`: FR `Livraison au destinataire`; ES `Entrega al destinatario`; AR `تسليم المتلقي`. Broadly aligned with **Recipient delivery**.
- `notifications.deliveryChannels`: FR `Destinations nommées`; ES `Destinos con nombre`; AR `وجهات محددة`. Broadly aligned with **Named destinations**.
- `notifications.latestActivity`: all three localizations preserve **Notification Center 2.0**.
- `notifications.statuses.pending`: FR `Envoi`, ES `Envío`, AR `الإرسال` all mean sending, but the **state key remains `pending`**. The wording can indicate active sending rather than queued/pending; domain owner must confirm actual status semantics before approval.
- `notifications.bulk.previewSummary`: FR uses `déjà en cours` (already in progress), not **already current**. ES `ya actual` is closer; AR `ساري المفعول بالفعل` seems closer to already in effect but requires native validation.
- `notifications.bulk.confirmDescription`: FR/ES preserve action/count placeholders and appear semantically close. AR `تطبق الرسائل المستعرضة من {operation} إلى {count}.` needs native review for the actual operation/count and confirmation meaning.
- `notifications.types.giftCodeExpiring`: AR `رمز الهدية` lacks the **expiring** qualifier; risk of presenting expiry as a generic Gift Code.
- `notifications.bulk.selectVisible`: FR `Sélectionner la couleur montrée` refers to a **colour**, not visible inbox messages. AR `حدد العرض` warrants review.
- `notifications.deliverySecurity`: FR/ES/AR express encryption and browser non-disclosure, but source **“scoped to the active Governor”** is not securely equivalent to the common FR/ES “extended to Governor” wording. Treat the English security assertion itself as requiring evidence from the product/security owner.

## Review routing and release gate

Submit `notifications.statuses.pending`, `notifications.bulk.previewSummary`, `notifications.bulk.confirmDescription`, `notifications.deliverySecurity` and related bulk confirmation keys to [reviewer recruitment issue #182](https://github.com/awalker0878/kingshot-alliance/issues/182) for first-wave triage. Those four exact keys are **not in the strict 511 candidate list**; only `bulk.previewSummary` appears in the broader pre-filter semantic inventory. This is a known gap in the exploratory screen, not permission to change the 511 count or silently pass them.

Owner review must establish source/back-end semantics, then qualified native-language reviewers must approve translations. Until then these entries remain `review_required`. **Do not conflate translation QA with the PR3 zero-diff structural refactor.**
