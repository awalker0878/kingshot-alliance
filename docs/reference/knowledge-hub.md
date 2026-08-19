# Knowledge hub provenance

Status: Current

The Alliance content hub stores guides, Event instructions, reference pages, rules, and announcements. Guides, Event instructions, and reference pages are treated as knowledge content and cannot be saved or published without a source label and review date.

## Curation workflow

1. Choose a knowledge content type and write the localized Alliance guidance.
2. Record a human-readable source label. Add a credential-free HTTPS source URL when one is available.
3. Record the relevant game version when the guidance is version-sensitive.
4. Set the date on which an Alliance editor last checked the guidance.
5. Save the draft, review it, and publish or schedule it.

Source, game-version, and review-date metadata is copied into every immutable revision. Restoring a revision restores its provenance and returns the item to draft so it must be published explicitly.

## Trust boundary

- External community projects are discovery inputs, not authoritative game data.
- The application does not infer a strategy claim, game version, or cost table.
- Source URLs must use HTTPS and cannot contain embedded credentials.
- A source URL is optional because an Alliance may use a documented internal review, but the source label and review date are mandatory for knowledge content.
- Existing unreviewed knowledge remains visible after the schema change, but it must be curated before it can be republished.

Rules and announcements can carry provenance, but it is not mandatory because they may be Alliance-authored policy or operational communication rather than claims about game behavior.
