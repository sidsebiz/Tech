# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Repository foundation: prototype reference, wp-env and Docker Compose environments, tooling, CI and deploy workflows.
- Content model in Techvenza Core: article meta (reading time, format, guide level, key points, featured/trending/editor's-pick flags, assigned editor, disclosures, corrections, SEO), category term meta (colours, icon, intro, "what to watch", hero image, SEO, homepage flag, order) with contrast check, Series taxonomy, author/reader user meta with profile photo, private CPTs (applications, campaigns, contact messages, redirects), custom tables (subscribers, post views, reading history, notifications) with versioned migrations, encrypted settings store and block-editor document sidebar.
- Blocks: 54 dynamic blocks (header, navigation with mega-menu, search overlay with live suggestions, footer, homepage sections, listing pages, category/author/article/search/404 templates, document layout) with sidebar controls, server rendering and crawlable filter URLs enhanced in place through a fragment REST endpoint; REST routes for session, search suggestions, reader bookmarks/topics/history and view beacons; article reading tools (progress, table of contents, text size, dark reading theme).
- Editorial workflow: custom statuses, server-side transition engine with checklist, notes, scheduling and timeline; roles (applicant, author, editor, managing editor) with capability locks and suspension; in-app notifications and template emails on every transition; REST endpoints for workflow state/transitions and notifications.
- `wp techvenza seed|reset|migrate|aggregate`: imports the prototype's categories, authors, 26 articles, 11 workflow items, applications, subscribers, campaigns, redirects, notifications, pages and menus (images sideloaded from Unsplash unless `--skip-images`); demo passwords are printed once.
- Accounts: branded login, registration with email verification, forgot/reset password on the core key flow (branded emails), password change and email change with confirmation, GDPR deletion requests, login throttling, honeypot, Cloudflare Turnstile / reCAPTCHA v3, optional Google sign-in, wp-login.php redirects and wp-admin lockdown for readers.
- Reader dashboard (overview, saved, history, preferences & profile) and author portal (dashboard, my articles, submission details with feedback and timeline, author profile with photo upload, analytics from first-party view counts, notifications, account settings) in a shared app shell; writing happens in the block editor.
- Editorial admin (wp-admin → Editorial): dashboard with pipeline stats and charts, review queue with bulk actions and a detail view (preview, checklist, feedback, timeline, note-required transitions), drag-to-reschedule editorial calendar with an accessible agenda, author applications with approve/decline and account provisioning, authors screen (visibility, featured and staff flags, suspend/reactivate), analytics with period comparison and CSV export, editable roles & permissions matrix, and workflow/editor/views columns on the posts list.
- Tests: PHPUnit suite for the workflow engine and Playwright end-to-end suite with axe accessibility scans.
