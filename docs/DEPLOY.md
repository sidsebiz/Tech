# Deploy

## Hosting requirements

* PHP 8.2+ with `sodium`, `intl`, `mysqli`; WordPress 6.6+ (tested on 6.8); MySQL 8 or MariaDB 10.6+
* HTTPS, outbound HTTPS (SMTP relay, Brevo/Mailchimp, captcha, Google OAuth), WP-CLI on the server
* A real cron: set `define( 'DISABLE_WP_CRON', true );` and run every 5 minutes
  `wp --path=/path/to/wordpress cron event run --due-now` (hourly view aggregation, daily clean-up,
  newsletter batches, scheduled campaigns and posts depend on it)
* Optional: a persistent object cache (Redis/Memcached) and a full-page cache. Public pages carry no
  personal data (the session is fetched separately), so they can be cached; exclude `/wp-admin`,
  `/wp-json`, `/login/`, `/register/`, `/dashboard/` and `/author-portal/`.

## First install

1. Install WordPress, then upload `techvenza-core-<version>.zip` (Plugins → Add New → Upload) and
   `techvenza-theme-<version>.zip` (Appearance → Themes → Upload). Activate both.
2. Add secrets to `wp-config.php` (see *Configuration* below). Make sure `AUTH_KEY` and `SECURE_AUTH_KEY`
   are set — they derive the encryption key for secrets stored in the database.
3. Optional demo content: `wp techvenza seed`. Remove it before launch with `wp techvenza reset --yes`.
4. **Settings → Permalinks** → *Post name* (the seeder sets this automatically).
5. Work through **Editorial → Settings**, using each *Save & test* button.

## Configuration: settings, constants and secrets

Every setting can be entered in **Editorial → Settings** or pinned in `wp-config.php` as
`TECHVENZA_<KEY>`; constants win and show as locked in the screen. Secrets typed into the screen are
encrypted with libsodium (key derived from the site salts) and never displayed again. **No secret is
stored in this repository.**

```php
// wp-config.php (examples — use your own values)
define( 'TECHVENZA_SMTP_ENABLED', true );
define( 'TECHVENZA_SMTP_HOST', 'smtp.example.com' );
define( 'TECHVENZA_SMTP_PORT', 587 );
define( 'TECHVENZA_SMTP_ENCRYPTION', 'tls' );
define( 'TECHVENZA_SMTP_USER', 'apikey' );
define( 'TECHVENZA_SMTP_PASS', '…' );
define( 'TECHVENZA_MAIL_FROM', 'newsroom@example.com' );
define( 'TECHVENZA_ESP_PROVIDER', 'brevo' );          // local | brevo | mailchimp
define( 'TECHVENZA_BREVO_API_KEY', '…' );
define( 'TECHVENZA_BREVO_LIST_ID', 3 );
define( 'TECHVENZA_CAPTCHA_PROVIDER', 'turnstile' );  // none | turnstile | recaptcha
define( 'TECHVENZA_TURNSTILE_SITE_KEY', '…' );
define( 'TECHVENZA_TURNSTILE_SECRET', '…' );
define( 'TECHVENZA_GOOGLE_CLIENT_ID', '…' );
define( 'TECHVENZA_GOOGLE_CLIENT_SECRET', '…' );
```

Rotating `AUTH_KEY`/`SECURE_AUTH_KEY` makes secrets saved through the screen unreadable — re-enter them
afterwards (constants are unaffected).

## Continuous integration

`.github/workflows/ci.yml` runs on every push and pull request: PHP syntax, PHPCS (WordPress
standards), PHPStan level 6, ESLint/Stylelint and build, PHPUnit on PHP 8.2 and 8.3 against MySQL,
Playwright end-to-end tests with axe accessibility scans, and a Lighthouse CI budget.

## Releases and deployment

`.github/workflows/deploy.yml` builds `dist/techvenza-<version>.zip` (plus separate theme and plugin
ZIPs) on every `v*` tag or manual run, and — once the secrets below exist — deploys over SSH with
rsync, then runs `wp plugin activate`, `wp theme activate`, `wp techvenza migrate`, `wp cache flush` and
`wp rewrite flush`. Without the secrets the deploy job is skipped and the ZIP is still attached to the run.

```bash
npm version patch          # bumps package.json and creates the tag
git push --follow-tags     # CI builds and deploys
```

Manual deploys: *Actions → Deploy → Run workflow* and choose **staging** or **production** (GitHub
environments; add protection rules such as required reviewers for production).

### GitHub Actions secrets

| Secret | Value |
| --- | --- |
| `DEPLOY_HOST` | Server hostname or IP |
| `DEPLOY_USER` | SSH user that owns the WordPress files |
| `DEPLOY_PORT` | SSH port (optional, default 22) |
| `DEPLOY_PATH` | Absolute path to the WordPress root (the folder containing `wp-content`) |
| `DEPLOY_SSH_KEY` | Private key (ed25519 recommended) whose public key is in the server user's `authorized_keys` |
| `DEPLOY_KNOWN_HOSTS` | Output of `ssh-keyscan -p <port> <host>` |

Set them per environment (*Settings → Environments → staging / production*) to deploy to different servers.

### Rollback

Re-run the Deploy workflow for the previous tag (*Actions → Deploy → Run workflow → Use workflow from:
tag*). Database migrations are additive, so older code keeps working with a newer schema.

## Launch checklist — information and keys needed

| Area | What we need | Where it goes |
| --- | --- | --- |
| Domain & hosting | Production (and staging) domain, SSH access, PHP/MySQL versions | Server; `DEPLOY_*` secrets |
| Deploy | `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT`, `DEPLOY_PATH`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS` | GitHub → Settings → Secrets and variables → Actions (or per environment) |
| Email delivery | SMTP host, port, encryption, username, password/API key; verified sender address and name; SPF, DKIM and DMARC records for the sending domain | `TECHVENZA_SMTP_*`, `TECHVENZA_MAIL_FROM(_NAME)` or Settings → Email |
| Newsletter | Provider choice; Brevo API key + list ID **or** Mailchimp API key + audience ID; newsletter sender name/email | Settings → Newsletter / constants |
| Bot protection | Cloudflare Turnstile site key + secret **or** reCAPTCHA v3 site key + secret | Settings → Forms & Spam / constants |
| Contact routing | Inboxes for editorial, corrections, partnerships, contributor support and other | Settings → Forms & Spam |
| Google sign-in (optional) | OAuth client ID + secret; add the redirect URI shown in Settings → Login to the Google console | Settings → Login / constants |
| Analytics (optional) | GA4 measurement ID **or** Plausible domain (+ script URL if self-hosted) | Settings → Analytics |
| Search Console | Verification code; submit `/wp-sitemap.xml` | Settings → Analytics |
| Brand | Logo (light and dark), favicon (512×512), default social image (1200×630), brand colours if different | Settings → Brand / SEO defaults |
| Social | LinkedIn, X, YouTube, Facebook, Instagram URLs; X handle | Settings → Header & Footer / SEO defaults |
| Legal | Company name and address; reviewed Privacy Policy, Terms and cookie wording; then switch off the "draft — requires legal review" banner | Pages; Settings → Privacy & Cookies |
| People | Real editors and authors (names, titles, photos, bios); remove demo users and content | Users; `wp techvenza reset --yes` |
| Images | Licensed images for articles (the seeder uses Unsplash URLs for demo purposes only) | Media Library |
| Operations | Server cron for `wp cron event run --due-now`, backups, uptime monitoring, object/page cache | Hosting |
