# Recruitment discovery

Status: Current

The public recruitment board helps Governors find Alliances that have deliberately opted in to discovery. It is available at `/recruitment`.

## Alliance listing controls

A recruiter with Recruitment management permission controls four independent settings:

- application mode: public, invitation-only, or closed;
- applications open/closed;
- public-board listing consent;
- the public title and introduction.

A listing is visible only while the Alliance is active, the mode is public, applications are open, and public-board listing consent is enabled. Turning off any one of those conditions removes it from discovery without changing candidate records.

## Governor journey

Governors can search Alliance names, recruitment titles, and introductions, then filter by Kingdom or language. The current filtered URL is shareable. Each result provides the Alliance public profile and its application form.

The board returns at most 100 matches. This protects the public query and prompts a narrower search when the result set is large.

## Attribution and privacy

Known public entry points use bounded source labels:

| Entry point | Stored source |
| --- | --- |
| Recruitment board | `recruitment-board` |
| Alliance public page | `alliance-public-page` |
| Recruiter share link | `alliance-share` |
| Alliance bot command | `bot-command` |

Bot adapters use the same public application path and tag links with `bot-command`; they do not create or read candidate records. The application form visibly identifies a preset source. If no known source is present, the applicant can optionally answer “How did you hear about us?”

This is coarse application metadata. It does not set an analytics identifier, follow a Governor between pages, or expose candidate information on the public board.

## Conversion view

The private Recruitment Hall groups each source by:

- submitted applications;
- accepted applications and accepted rate;
- joined applicants and joined rate.

Merged candidate records are excluded, matching the existing recruitment summary. “Accepted” includes joined candidates because joining follows acceptance.
