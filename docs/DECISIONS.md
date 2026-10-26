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

## D4 — CSS ported from the prototype, tokens bound to theme.json
The prototype stylesheet was split into `main.css`, `article.css` and `app.css`. All colour,
type, spacing, radius and shadow variables now resolve to `theme.json` presets
(`--ink: var(--wp--preset--color--ink)` …), so palette changes in the Site Editor
(Styles → Colours) or in *Techvenza → Settings → Brand* restyle the whole site.
`article.css` and `app.css` are attached with `wp_enqueue_block_style()` and only print
on pages that render those blocks.

## D5 — Menus use classic menu locations rendered by a block
Core's Navigation block cannot host a dynamic categories mega-menu, cannot be pointed at
"footer column 3" from a theme file without hard-coding a post ID, and stores menus as
`wp_navigation` posts that the seeder could not reference portably. The `techvenza/nav-menu`
block renders a classic menu *location* instead. Every menu is edited in
**Appearance → Menus** (drag-and-drop, nested items for dropdowns). A menu item whose
CSS class is `tv-mega-categories` becomes the dynamic Categories mega-menu (icon, colour, count).

## D6 — Sticky header on the template-part wrapper
The header template part is rendered with `className: tv-header-part` and is the sticky
element (offset by the admin bar), because a sticky child of an equally tall wrapper never sticks.

## D7 — Progressive enhancement for reveal animations
`html.tv-js` is set inline in `<head>`; `.reveal` content is only hidden when that class exists,
so content is always visible without JavaScript and animations respect `prefers-reduced-motion`.
