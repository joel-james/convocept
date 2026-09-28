# Convocept – Fast Native Comment System

The fastest comment system for WordPress.

Convocept replaces the default WordPress comment UI with a modern, server-rendered thread that
loads **zero JavaScript until readers reach the comments**. Every comment stays in the native
`wp_comments` table: no external service, no tracking, nothing lost if you uninstall.

- Website: [convocept.com](https://convocept.com)
- WordPress.org: [wordpress.org/plugins/convocept](https://wordpress.org/plugins/convocept/)
- By [Foxe Labs](https://foxelabs.com)

> **Status:** version 1.0.0 is in development on the `dev` branch. See [PLAN.md](PLAN.md) for
> the roadmap.

## Why Convocept

- **Fast:** 0 KB JS on page load, interaction JS ≤ 8 KB gzipped, CSS ≤ 5 KB gzipped, no layout
  shift. Budgets are enforced in CI.
- **Native:** comments live in core tables, so Akismet, anti-spam plugins, exports and admin
  screens keep working.
- **Page-cache safe:** no per-user data in cached HTML; per-user state loads lazily over REST.
- **Works without JavaScript:** the composer is a real form that falls back to core posting.
- **SEO friendly:** comments are in the page HTML, with structured data.
- **Audience:** reply and post subscriptions with double opt-in and one-click unsubscribe.
- **Insights:** see how your discussions are doing, with no tracking (1.1).
- **Easy migration:** import from Disqus (1.0), Subscribe to Comments Reloaded and wpDiscuz (1.1).

## Requirements

- WordPress 6.5+
- PHP 7.4+

## Development

Requires PHP, Composer, Node.js (see `.nvmrc`) and Docker (for `wp-env`).

```bash
composer install && npm install
npm run build        # build front-end (esbuild) and admin (wp-scripts) assets
npm run env:start    # start wp-env: dev site on :8888, tests site on :8889
```

| Command | What it does |
|---|---|
| `npm run build` | Build JS/CSS into `assets/dist/` |
| `npm run start` | Rebuild front-end assets on change |
| `npm run size` | Check bundle sizes against the budget |
| `npm run test:php` | PHPUnit inside wp-env |
| `npm run test:e2e` | Playwright end-to-end tests |
| `composer lint` | phpcs (WordPress Coding Standards) + PHPStan level 6 |
| `npm run lint` | ESLint, stylelint, Prettier |
| `npm run release` | Build a release zip into `dist/` |

### Project layout

```
convocept.php     Plugin bootstrap
src/              PHP (PSR-4, namespace Convocept\)
templates/        Theme-overridable PHP templates
assets/src/       Front-end (vanilla JS/CSS) and admin (React) sources
tests/            PHPUnit (php/) and Playwright (e2e/) tests
docs/             Decisions and hooks reference
bin/              Build and release scripts
```

### Contributing

- Work happens on `dev`; `main` holds reviewed milestones.
- Read [CLAUDE.md](CLAUDE.md) for conventions and the definition of done, and
  [PLAN.md](PLAN.md) for architecture and milestones.
- Extension hooks are documented in [docs/hooks.md](docs/hooks.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
