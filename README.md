# Techvenza

A fully dynamic WordPress publication for technology, business and industry, built from the
approved HTML prototype in [`prototype/index.html`](prototype/index.html).

| Part | Path | Responsibility |
| --- | --- | --- |
| **Theme** | `wp-content/themes/techvenza` | Block (FSE) theme — design tokens, templates, template parts, patterns, CSS, front-end JS. Presentation only. |
| **Plugin** | `wp-content/plugins/techvenza-core` | Everything else — content model, dynamic blocks, editorial workflow, roles, portal, admin CMS, newsletter, SEO, views, settings, WP-CLI seeder. |

Switching themes never loses content or features: every block, setting and data structure lives in the plugin.

## Requirements

* PHP 8.2+, WordPress 6.6+ (tested on 6.8), MySQL 8 / MariaDB 10.6+
* Node 20+ and Docker (for local development)
* Composer 2 (for PHP linting and tests)

## One-command setup

```bash
npm install && composer install && npm start && npm run seed
```

* Site: <http://localhost:8888> — admin login `admin` / `password`
* The seeder prints one-time passwords for the demo users (reader, author, editor, managing editor, admin).
* Email capture: run `docker run -d -p 8025:8025 -p 1025:1025 axllent/mailpit` and open <http://localhost:8025>.

### Docker Compose alternative

```bash
cp .env.example .env
docker compose up -d                       # WordPress :8080, phpMyAdmin :8081, Mailpit :8025
docker compose run --rm cli core install --url=http://localhost:8080 --title=Techvenza \
  --admin_user=admin --admin_password=password --admin_email=admin@example.com
docker compose run --rm cli plugin activate techvenza-core
docker compose run --rm cli theme activate techvenza
npm run seed:docker
```

## Scripts

| Command | What it does |
| --- | --- |
| `npm start` / `npm run stop` | Start / stop the wp-env environment |
| `npm run build` | Minify CSS/JS (`*.min.*`) used when `SCRIPT_DEBUG` is off |
| `npm run seed` / `npm run seed:reset` | Import / remove demo content from the prototype |
| `npm run lint` | ESLint, Stylelint, PHPCS (WordPress standards), PHPStan level 6 |
| `npm test` | PHPUnit (inside wp-env) + Playwright end-to-end incl. axe accessibility scans |
| `npm run lighthouse` | Lighthouse CI budget for home, article and category |
| `npm run release` | Build deployable ZIPs into `dist/` |

## Documentation

* [`docs/ADMIN-GUIDE.md`](docs/ADMIN-GUIDE.md) — editing pages, menus, header/footer, categories, authors and the editorial workflow
* [`docs/CONTENT-MODEL.md`](docs/CONTENT-MODEL.md) — post meta, taxonomies, user meta, custom post types and tables
* [`docs/DEPLOY.md`](docs/DEPLOY.md) — hosting, secrets, release and rollback
* [`docs/DECISIONS.md`](docs/DECISIONS.md) — architecture decisions and trade-offs

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
