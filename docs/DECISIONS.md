# Architecture decisions

Each entry records a decision, why it was made and what it costs. Newest last.

## D1 — Theme for presentation, plugin for everything else
All dynamic blocks, data structures, REST endpoints, admin screens and settings live in
`techvenza-core`. The theme ships `theme.json`, templates, parts, patterns, CSS and small
front-end scripts. *Trade-off:* block styles are written against the theme's CSS classes,
so a replacement theme renders the blocks with the plugin's minimal base styles until it styles them.

## D2 — Local environment: wp-env first, Docker Compose as alternative
`@wordpress/env` gives a one-command setup (`npm start`) and a separate tests site for PHPUnit.
Docker Compose additionally provides MariaDB, Mailpit and phpMyAdmin for people who prefer it.

## D3 — Fonts are self-hosted variable WOFF2 files
Inter (wght axis) and Fraunces (opsz + wght axes) are copied from the `@fontsource-variable`
packages into `themes/techvenza/assets/fonts` and declared through `theme.json` `fontFace`
with `font-display: swap`. No request to Google Fonts is made.
