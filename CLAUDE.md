# CLAUDE.md — Convocept

Read `PLAN.md` before starting any task. Work one milestone at a time, in order.

## What this is
Convocept is a free WordPress.org plugin (slug `convocept`, by Foxe Labs, website convocept.com):
a very fast, self-hosted replacement for the default WordPress comment UI. Comments stay in the
native `wp_comments` / `wp_commentmeta` tables. Paid features ship later as separate add-on plugins.

**Current state:** the code in this repo is the earlier React prototype, renamed to Convocept
(WordPress.org rejected "Comments" and then "Native Comments" as too generic; "Foxe Comments" was dropped
so the product stands alone from the company brand) and submitted to WordPress.org to reserve the slug. It does not follow the architecture below yet.
Until approval, only fix review-team feedback; M0 starts after approval (PLAN.md "Pre-M0").

## Non-negotiables
- **Front end: vanilla JS only.** No React, Preact, jQuery, or frameworks on public pages.
  React is allowed only in wp-admin (via `@wordpress/element`, already loaded there).
- **Server-rendered HTML.** PHP renders the thread. AJAX/REST responses return rendered HTML
  fragments from the same PHP templates. Never duplicate markup in JS.
- **Performance budget (enforced in CI):** 0 KB JS on initial page load; interactive JS ≤ 8 KB
  gzipped; CSS ≤ 5 KB gzipped; 0 extra requests on page load; no layout shift.
- **Page-cache safe.** No per-user output in the cached HTML (no nonces, no commenter cookies
  printed server-side). Per-user state is fetched lazily via REST.
- **Security first.** Escape everything on output, sanitize on input, capability-check every
  REST route, never run `do_shortcode()` on user content, never trust `X-Forwarded-For` unless a
  trusted proxy is configured, rate-limit every write endpoint. See PLAN.md §7.
- **No new runtime dependencies** without asking. Dev dependencies are fine.
- **WordPress.org guidelines:** no trialware, no locked features, no phoning home, no admin
  hijacking. Everything in this repo is fully functional and GPLv2+.

## Conventions
- Prefixes: everything uses the plugin name `convocept` (WordPress.org reviewers reject short
  prefixes like `nc_`, and the prefix should match the plugin):
  - PHP namespace `Convocept\`, PSR-4 autoload from `src/`.
  - Global functions, hooks, options, meta keys, transients, table names: `convocept_`
    (private comment meta: `_convocept_`).
  - Constants: `CONVOCEPT_`.
  - CSS classes, custom properties, data attributes: `convocept-` (`--convocept-`, `data-convocept-`).
    Script/style handles: `convocept` / `convocept-*`.
  - JS globals: `convocept` / `convocept*` (e.g. `window.convoceptConfig`).
  - REST and Abilities namespace: `convocept/`.
- Text domain `convocept`. All user-facing strings translatable.
- Minimum: WordPress 6.5, PHP 7.4. Newer APIs (Abilities API, AI Client) via feature detection only.
- Coding standards: WordPress Coding Standards (phpcs), PHPStan level 6, ESLint + Prettier for JS.
- Hooks: every major step exposes `do_action` / `apply_filters` so add-ons can extend it.

## Commands
- `composer install && npm install` — set up
- `npm run build` — build JS/CSS with esbuild into `assets/dist/`
- `npm run size` — check bundle sizes against the budget
- `npm run env:start` — start `wp-env`
- `composer test` — PHPUnit (inside wp-env)
- `npm run test:e2e` — Playwright
- `composer lint && npm run lint` — all linters

## Definition of done (every task)
1. Acceptance criteria in PLAN.md for that item are met.
2. Tests added/updated and passing; linters clean; size budget passes.
3. Works with JavaScript disabled (graceful fallback to core form POST).
4. Works on a classic theme (Twenty Twenty-One) and a block theme (Twenty Twenty-Five).
5. `CHANGELOG.md` updated. Commit with a clear message.
