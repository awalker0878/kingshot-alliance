# Alliance Content Management Guide

This guide is for alliance owners, leaders, and content managers who have the `content.manage` permission.

## Open the content manager

1. Sign in and verify your email address.
2. Switch to the alliance you want to manage.
3. Open the alliance overview.
4. Choose **Manage content**.
5. Reconfirm your password when requested before making privileged changes.

All changes apply only to the active alliance.

## Public alliance profile

The public profile controls:

- alliance name
- kingdom
- language
- alliance time zone
- description
- primary branding color
- logo and banner

Upload branding media first, then select the uploaded image for the logo or banner slot and save the public profile.

Only clean, active images belonging to the current alliance can be used as public branding.

Recruitment availability is not a content-profile setting. Recruitment settings are authoritative for whether applications are closed, public, or invitation-only. Authorized recruiters manage that state in the **Recruitment** workspace, and the public alliance page reads it directly.

## Categories

Categories organize alliance content and control ordering in browse/filter views.

Create a category with a display name, URL-safe slug, and optional sort order. A category cannot be deleted while content still references it; move or update the content first.

## Create content

Supported content types are:

- announcements
- guides
- rules
- event instructions
- reference pages

For each item choose:

- public or members-only visibility
- title and URL slug
- optional summary
- body text
- locale/language tag
- optional category
- sort order

Saved content starts as a **draft**. Authored text is stored/rendered as plain text rather than trusted HTML.

## Publish now or schedule

A draft can be:

- published immediately, or
- scheduled for a future time.

Scheduled publication is handled automatically by the application scheduler. Public content becomes visible only after a successful publication transition.

Members-only content is never exposed through the public alliance page.

## Edit published content

Editing a published item creates a new revision and returns the item to **draft**. This prevents an unreviewed edit from becoming public automatically.

Publish or schedule the revised item when it is ready.

## Revision history and restore

Every content save creates an immutable revision.

Restoring a historical revision does **not** silently republish it. Restore creates a new draft revision using the historical text, after which a content manager can review and publish it normally.

## Archive content

Archiving removes the item from public/member browse results without deleting its revision history. An archived item can later be edited/restored into draft form and explicitly republished.

## Media uploads

Allowed uploads are limited by configured MIME type and size. The default maximum is 8192 KiB.

Uploads are security-screened before they become media records. Rejected files are not retained. Media is stored on a private tenant-specific path; production uses durable S3-backed storage.

An image used as current logo/banner must be detached from the public profile before it can be archived.

## Search and filtering

Public and member content hubs can filter by search text, type, category, and locale. Search always starts from the viewer's allowed visibility boundary:

- anonymous visitors: published public content only
- active members: published public plus published members-only content

Drafts, scheduled items that have not been published, archived items, and revision history are never public search results.

## Date and time behavior

Publication timestamps are stored as absolute timestamps and displayed in the viewer's browser locale. Alliance time zone information remains visible so alliance-specific timing is explicit.

## Troubleshooting

If **Manage content** is unavailable, confirm that:

- the correct alliance is active
- your membership is active
- your role includes `content.manage`
- your email is verified

If a mutation redirects to password confirmation, confirm your password and retry the action.

If an upload is rejected, verify its MIME type/size and use a clean source file. Persistent scanner/storage failures should be reported to the platform operator rather than bypassed.
