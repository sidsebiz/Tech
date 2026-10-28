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

## Search & AI answers

Each article has a **Search & AI answers** panel in the editor sidebar: a focus keyword, a 40–80 word
answer-first summary (shown as "In short"), FAQs (shown at the end and marked up as FAQPage) and sources
(cited links). The panel scores the article for search and for AI answers and lists what is missing.
Site-wide switches are in **Settings → AI & answer engines**: `llms.txt`, Markdown versions (`…/md/`),
AI search and AI training crawlers (robots.txt) and IndexNow.

## Partners & backlinks

**Editorial → Partners** lists companies you link to. For each one set the website, the link type
(*Sponsored* for paid placements, *Nofollow*, *UGC* or *Follow* for editorial endorsements), optional
keywords (their first mention in each article becomes a link) and an optional end date. A logo
(featured image) and short description show in the **Partners** block. Outbound-link defaults (new tab,
default rel, auto-link limit) are in **Settings → Links & partners**. Turn auto-links off for one
article in its Search & AI answers panel.

## Images

Articles without a featured image use, in order: the category's **Category image** (Posts → Categories),
**Settings → Brand → Default cover**, then the topic illustration from the Media Library (search
"Techvenza illustration"). Replace any of them in the Media Library ("Replace" keeps the same item) or
choose another image — no code involved. Editors and administrators can upload SVG files; they are
cleaned automatically.

## Bulk Blog

**Editorial → Bulk Blog** imports many articles from one CSV file.

1. Download the template (or start from `docs/examples/bulk-blog-sample.csv`). Save as CSV (UTF-8);
   commas, semicolons and tabs all work, and header names are matched loosely (`post_title`, `Title`…).
2. Optionally add a ZIP of images and refer to them by file name in `featured_image` or in the content.
3. Choose the default status, what to do when a slug already exists (skip, update, or create a copy),
   whether to create missing categories and whether to import images found in the content.
4. **Upload and check** shows every row with errors and warnings; rows with errors are skipped.
5. **Import** runs in batches with a progress bar; download the results report afterwards.

| Column | Required | What to put in it |
| --- | --- | --- |
| `title` | yes | Headline |
| `content` | yes | HTML, Markdown or plain text (detected automatically) |
| `slug` |  | URL slug; matches existing articles for updates |
| `excerpt` |  | Card summary |
| `author` |  | Email, username or display name; empty = you |
| `category` |  | Name or slug, several with `|`, `Parent > Child` for subtopics |
| `tags` |  | Separated by `|` or commas |
| `status` |  | draft, pending, publish, future or private |
| `publish_date` |  | `YYYY-MM-DD HH:MM` (site time zone); future + publish = scheduled |
| `featured_image` |  | Public URL, or a file name inside the images ZIP |
| `image_alt` |  | Alt text |
| `image_caption` |  | Caption / credit |
| `meta_title` |  | SEO title |
| `meta_description` |  | Search snippet |
| `focus_keyword` |  | Main search phrase |
| `summary` |  | Answer-first "In short" text |
| `faq` |  | `Question|Answer||Question|Answer` or JSON |
| `sources` |  | `Title|URL||Title|URL` |
| `key_points` |  | `Point||Point` |
| `article_type` |  | deep-dive, analysis, guide, opinion, news |
| `featured / editors_pick / noindex` |  | yes/no |
| `canonical_url, series, sponsored` |  | Optional |

From the command line: `wp techvenza import-csv articles.csv --images=images.zip --dry-run`.

## Demo content

`wp techvenza seed` imports the prototype's content; `wp techvenza reset --yes` removes exactly what the
seeder created (anything you made yourself is kept). Delete the demo users before launch.
