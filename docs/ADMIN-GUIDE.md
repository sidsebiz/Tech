# Admin guide

Everything on the site is edited in **wp-admin**. Nothing requires code changes. This guide is
written for editors and site owners; developers will find internals in `CONTENT-MODEL.md` and
`DECISIONS.md`.

## Where things live

| You want to change… | Go to |
| --- | --- |
| Logo, colours, tagline, favicon | **Editorial → Settings → Brand** |
| Header button, announcement strip, footer text, social links | **Editorial → Settings → Header & Footer** |
| Navigation links (header, mobile drawer, four footer columns, legal links) | **Appearance → Menus** (locations are named after where they appear) |
| Homepage sections — order, wording, which categories are spotlighted | **Appearance → Editor → Templates → Front Page**; quick on/off switches in **Settings → Homepage** |
| Any page (About, Become an Author, Guidelines, legal pages…) | **Pages** — every section is a block with its own sidebar settings |
| Articles | **Posts** (block editor; the *Techvenza* sidebar holds article type, key points, flags, disclosures, corrections and SEO) |
| Categories (colour, icon, intro, "what to watch", hero image, SEO, homepage visibility, order) | **Posts → Categories** |
| Series | **Posts → Series** |
| Authors and their public profiles | **Editorial → Authors**, and each user's profile screen |
| Email wording | **Editorial → Settings → Editorial → Email templates** |
| SMTP, newsletter provider, captcha, Google sign-in, analytics | **Editorial → Settings** (each has a *Save & test* button) |
| Cookie banner text, company details, legal-draft banner | **Editorial → Settings → Privacy & Cookies** |

Blocks that list content (latest stories, trending, category grids, author directories…) have
sidebar controls for counts, filters, headings and layout. Section blocks have *surface* and
*spacing* options so you can re-arrange pages without breaking the design.

## Editorial workflow

```
Draft ──submit──▶ New submission ──start review──▶ In review ──approve──▶ Approved ──schedule──▶ Scheduled ──▶ Published
                         ▲                              │
                         └──── resubmit ◀── Changes requested (note required)
                                                        └── reject (note required) ──▶ Rejected
```

* **Authors** write in the block editor and submit from the author portal or the editor. A submission
  must pass the checklist (title, excerpt, category, minimum words).
* **Editors** work from **Editorial → Review queue**: open a submission to preview it, tick the review
  checklist, read the timeline and feedback, then *Start review*, *Request changes*, *Approve* or *Reject*.
  Requests and rejections need a note — it is emailed to the author and kept in the timeline.
* **Managing editors** schedule and publish. In **Editorial → Editorial calendar** drag an approved
  article from the tray (or a scheduled one) onto a day; keyboard users schedule from the review screen.
* Every transition sends an in-app notification and an email (each person can turn email groups off).
* The review checklist items are edited in **Settings → Editorial**.

## Author applications

**Editorial → Author applications** lists applications from the *Apply to write* form. Open one to
approve, decline or ask for more information (all send the matching email). Approving creates the
author account — or upgrades an existing reader — and emails a link to set a password.

## Authors

**Editorial → Authors** shows every contributor and editor. Toggle *Profile visible*, *Featured*
(homepage and directory) and *Staff* (badge), or *Suspend* an account: suspended people are signed
out, lose portal access and keep their published articles.

## Newsletter

* Sign-ups use **double opt-in**: nobody receives a campaign until they click the confirmation link.
  Each subscriber row keeps the consent time and wording.
* **Editorial → Newsletter → Subscribers**: search, filter by status or topic, export CSV, unsubscribe,
  or *Erase* (for deletion requests).
* **Draft campaign**: pick stories, write the editor's note, *Send test* to yourself, *Preview*, then
  *Schedule* or *Send now*. "All subscribers" campaigns go through Brevo or Mailchimp when connected;
  topic campaigns are sent by WordPress in background batches with one-click unsubscribe.

## Contact messages

**Editorial → Messages** collects contact-form messages. Each inquiry type is emailed to the address set in
**Settings → Forms & Spam** (empty routes go to the site admin email). Reply from your email client
(Reply-To is the sender), then *Mark as handled*. Messages are deleted automatically after the
retention period you set.

## Analytics

**Editorial → Analytics** uses first-party view counts (no cookies, bots excluded): daily views compared
with the previous period, top articles, categories and authors, with CSV export. For audience
analytics connect GA4 or Plausible in **Settings → Analytics** — they load only for visitors who choose
"Allow all" in the privacy banner.

## Roles & permissions

**Editorial → Roles & permissions** (administrators only) shows what each role can do. Tick or untick a
permission and save; each row grants the related WordPress capabilities listed under it. *Reset to
defaults* restores the original set.

## SEO

Titles, descriptions, canonical URLs, social cards and structured data are automatic. Override them per
article (block editor sidebar → SEO) or per category. Site-wide defaults — separator, default
description and image, X handle, noindex for tag/author archives — are in **Settings → SEO defaults**.
Account and dashboard pages are never indexed. If you install Yoast SEO, Rank Math, SEOPress or AIOSEO,
the built-in tags switch off automatically.

## Demo content

`wp techvenza seed` imports the prototype's content; `wp techvenza reset --yes` removes exactly what the
seeder created (anything you made yourself is kept). Delete the demo users before launch.
