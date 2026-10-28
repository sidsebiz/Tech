# Content model

Everything below is registered by the **Techvenza Core** plugin, so it survives a theme switch.
Meta keys are prefixed `tv_`; editable keys are registered with `show_in_rest` and appear in the
block editor's *Techvenza* sidebar, on the category screen or on the user profile screen.

## Articles (`post`)

| Meta key | Type | Purpose |
| --- | --- | --- |
| `tv_reading_time` | int | Minutes; recalculated on save unless `tv_reading_time_manual` is on |
| `tv_reading_time_manual` | bool | Keep a hand-entered reading time |
| `tv_article_type` | string | `deep-dive`, `analysis`, `guide`, `opinion` or `news` (card label and format filters) |
| `tv_guide_level` | string | `Beginner` / `Intermediate` / `Advanced` for guides |
| `tv_key_points` | string[] | "Key takeaways" box at the top of the article |
| `tv_is_featured` | bool | Eligible for the homepage hero and featured grid |
| `tv_is_trending_manual` | bool | Pinned to the top of trending lists |
| `tv_is_editors_pick` | bool | Editor's Picks page and section |
| `tv_assigned_editor` | int | User ID of the reviewing editor (set by the workflow) |
| `tv_scheduled_note` | string | Internal note shown in the calendar |
| `tv_ai_disclosure` / `tv_ai_disclosure_text` | bool / string | AI-assistance disclosure at the end of the article |
| `tv_conflict_disclosure` | string | Conflict-of-interest disclosure |
| `tv_is_sponsored` / `tv_sponsor_name` | bool / string | Sponsored label |
| `tv_corrections` | {date,text}[] | Dated corrections listed at the end of the article |
| `tv_seo_title`, `tv_seo_description`, `tv_canonical_url`, `tv_noindex` | | Per-article SEO overrides |
| `tv_summary` | string | Answer-first summary ("In short"; Markdown, llms.txt, schema `abstract`) |
| `tv_focus_keyword` | string | Main search phrase (schema `keywords`, SEO checks) |
| `tv_faq` | {q,a}[] | FAQ section and FAQPage JSON-LD |
| `tv_sources` | {title,url,rel}[] | Cited sources (schema `citation`) |
| `tv_disable_autolinks` | bool | No partner auto-links in this article |
| `tv_imported` | string | Bulk Blog job that created or last updated the article |
| `tv_view_count`, `tv_views_24h`, `tv_views_7d`, `tv_views_30d` | int | Read-only view aggregates (hourly cron) |
| `tv_timeline`, `tv_submitted_at` | | Workflow history and queue order (written only by the workflow engine) |

**Statuses.** Besides core `draft`, `future` and `publish`: `submitted`, `in_review`, `changes_requested`,
`resubmitted`, `approved`, `rejected`, `archived`. They only change through
`Workflow::transition()` (see `docs/DECISIONS.md`, D12).

## Taxonomies

* **Category** (core) — top-level categories are the site's topics. Term meta: `tv_short` (short label),
  `tv_color` / `tv_ink` / `tv_soft` (accent, text and tint colours, with a live WCAG contrast check),
  `tv_icon`, `tv_intro`, `tv_watch` ("What to watch"), `tv_hero_image`, `tv_seo_title`,
  `tv_seo_description`, `tv_show_on_home`, `tv_order`. Child categories are the sub-topic filters.
* **Tags** (core) — free keywords; tag archives are `noindex` by default (Settings → SEO defaults).
* **Series** (`tv_series`, public, flat like tags) — multi-part series with its own archive.

## People (users)

| Role | Can |
| --- | --- |
| Reader (`subscriber`) | Save articles, follow topics, manage newsletter and account, apply to write |
| Contributor Applicant (`tv_applicant`) | As reader, with an open application |
| Author (`tv_author`) | Write drafts, submit for review, respond to feedback, author portal |
| Editor (`tv_editor`) | Review, request changes, approve, manage applications and authors, analytics |
| Managing Editor (`tv_managing_editor`) | Everything editors can, plus publish/schedule, categories, pages, newsletter, SEO, messages |
| Administrator | Everything, including settings and the roles matrix |

Profile meta (editable by the person): `tv_title`, `tv_expertise`, `tv_categories`, `tv_website`,
`tv_linkedin`, `tv_x`, `tv_photo`. Editorial meta (editors only): `tv_staff`, `tv_verified`, `tv_visible`,
`tv_featured`, `tv_account_status`. Private reader data (never exposed publicly): `tv_bookmarks`,
`tv_topics`, `tv_email_prefs`, `tv_nl_frequency`.

## Private post types

| Post type | Holds | Key meta |
| --- | --- | --- |
| `tv_application` | Author applications | `tv_email`, `tv_title`, `tv_org`, `tv_expertise`, `tv_categories`, `tv_bio`, `tv_link`, `tv_samples`, `tv_reason`, `tv_status`, `tv_reference`, `tv_user_id`, `tv_decision_note`, `tv_consent_at`, `tv_history` |
| `tv_campaign` | Newsletter campaigns | `tv_subject`, `tv_preheader`, `tv_intro`, `tv_posts`, `tv_audience`, `tv_status`, `tv_send_at`, `tv_sent_at`, `tv_stats`, `tv_esp_id` |
| `tv_message` | Contact form messages | `tv_name`, `tv_email`, `tv_type`, `tv_routed_to`, `tv_handled`, `tv_consent_at`, `tv_ip_hash` |
| `tv_partner` | Partners / backlinks (Editorial → Partners) | `tv_url`, `tv_rel` (follow/nofollow/sponsored/ugc), `tv_keywords`, `tv_until` |
| `tv_redirect` | 301/302 redirects (only when the Redirection plugin is not active) | `tv_from`, `tv_to`, `tv_code`, `tv_hits` |

## Custom tables (`{prefix}tv_*`, versioned migrations in `Database\Schema`)

| Table | Purpose |
| --- | --- |
| `tv_subscribers` | Newsletter subscribers: email, topics, frequency, status (`pending`/`confirmed`/`unsubscribed`), consent time/wording/IP hash, confirm and unsubscribe tokens, provider id, source |
| `tv_post_views` | Daily view counts per article (no personal data) |
| `tv_reading_history` | Signed-in readers' recently read articles |
| `tv_notifications` | In-app notifications for authors and editors |

## Settings

One option, `techvenza_settings`, edited at **Editorial → Settings**. Any key can be pinned from
`wp-config.php` with `TECHVENZA_<KEY>` (for example `TECHVENZA_BREVO_API_KEY`); secrets are encrypted at rest.

## REST API (`/wp-json/techvenza/v1`)

| Route | Method | Who |
| --- | --- | --- |
| `search/suggest` | GET | Anyone |
| `fragment/{block}` | GET | Anyone (server-rendered block fragment for in-place filters) |
| `views/{id}` | POST | Anyone (view beacon, bots ignored) |
| `auth/login`, `auth/register`, `auth/forgot`, `auth/reset`, `auth/resend` | POST | Anyone (same-origin) |
| `auth/google`, `auth/google/callback` | GET | Anyone (when Google sign-in is configured) |
| `me/bookmarks`, `me/topics`, `me/history`, `me/preferences`, `me/profile`, `me/password`, `me/deletion-request`, `me/notifications` | GET/POST/PUT/DELETE | Signed-in user |
| `portal/profile`, `portal/photo`, `portal/drafts/{id}` | POST/DELETE | Authors |
| `posts/{id}/workflow`, `posts/{id}/transition` | GET/POST | Per-transition capability |
| `calendar/move` | POST | Managing editors |
| `newsletter/subscribe` | POST | Anyone (same-origin) |
| `newsletter/unsubscribe/{token}` | POST | Token holder (RFC 8058 one-click) |
| `forms/contact`, `forms/apply` | POST | Anyone (same-origin) |
| `bulk/{job}/run` | POST | `tv_publish_posts` (runs the next Bulk Blog batch) |

Public text routes: `/llms.txt`, `/llms-full.txt`, `/{indexnow-key}.txt` and `/{slug}/md/` (Markdown version of an article or page).

The current-user summary and REST nonce come from `admin-ajax.php?action=techvenza_session`, so cached pages never contain personal data.

## WP-CLI

```bash
wp techvenza seed [--skip-images] [--demo-password=<pw>]   # import the prototype's content
wp techvenza reset --yes                                    # remove exactly what the seeder created
wp techvenza migrate                                        # run table/data migrations
wp techvenza aggregate                                      # recompute view aggregates now
wp techvenza illustrations [--force]                        # (re)import the illustration library
wp techvenza import-csv <file> [--images=<zip>] [--status=<s>] [--duplicates=skip|update|new] \
  [--no-create-terms] [--inline-images] [--dry-run] [--user=<id>]  # Bulk Blog from the command line
```
