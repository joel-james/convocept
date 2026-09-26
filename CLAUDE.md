# CLAUDE.md — Convocept

Read `PLAN.md` before starting any task. Work one milestone at a time, in order.

## What this is
Convocept is a free WordPress.org plugin (slug `convocept`, by Foxe Labs, website convocept.com):
a very fast, self-hosted replacement for the default WordPress comment UI. Comments stay in the
native `wp_comments` / `wp_commentmeta` tables. Paid features ship later as separate add-on plugins.

**Current state (2026-09-26):** rebuild toward release 1.0.0. The slug was reserved with an
unreleased React prototype (first git commit); the prototype's leftover files (`includes/`,
`build/`, React files in `src/`) are unused and excluded from release zips, pending deletion.

- **Done:** M0–M6 (scaffolding, server-rendered thread, composer, replies/load more/deep links,
  lazy loading + cache + live updates, votes/pins/moderation, subscriptions). Milestones are
  marked ✅ in PLAN.md §8; per-milestone decisions are in `docs/decisions.md` (newest first).
- **Next:** M7 structured data, then M8 (Disqus importer only for 1.0), M9 lite settings UI,
  M11, M12. There is **no admin settings screen yet** (M9); settings live in the
  `convocept_settings` option.
- **Branches:** work on `dev` (pushed to `origin`); `main` was last fast-forwarded at the
  1.0.0 renumbering, before M1. Merge to `main` only when the owner asks.
- **Open items to raise with the owner:** merge M1–M6 to `main`; delete the prototype files;
  Twenty Twenty-One shows its own dark submit button instead of our accent colour (not
  investigated); confirm the plan to ship basic AI checks free in 1.2 (decisions.md).

**Working environment notes**
- Docker Desktop must be running for `npm run env:start` (wp-env: dev :8888, tests :8889).
- `npm run test:php` reinstalls the tests site, so e2e specs set up their own state.
- The e2e helper mu-plugin (`tests/e2e/mu-plugins/`) is mapped as a **directory** into the tests
  site only; single-file mounts go stale. It disables flood/rate/timing checks, catches mail and
  exposes `/convocept-e2e/v1/{mails,run-queue}`. It returns early under PHPUnit.
- The tests site uses plain permalinks (`?p=123`): build URLs with `URL`/`URLSearchParams`.
- The in-app browser pane reports `visibilityState: hidden`, so IntersectionObserver never fires
  there and the lazy script doesn't load; verify front-end behaviour in Playwright.

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
  Add every new hook to `docs/hooks.md`.

## Coding and documentation standard
- **WordPress Coding Standards everywhere.** `composer lint` (phpcs WPCS + PHPCompatibility,
  PHPStan level 6) and `npm run lint` must pass with no ignores added without a `--` reason.
  Tabs, Yoda conditions, `array()` syntax, snake_case functions/methods/variables, full words.
  The only deliberate deviation: PSR-4 file names in `src/` (`src/Render/ThreadRenderer.php`),
  as allowed by the phpcs config.
- **Every file** starts with a file docblock: one-line summary, `@package Convocept`, `@since`.
- **Every class, interface, method and function** has a docblock following the
  [WordPress PHP documentation standards](https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/):
  summary line ending in a period; a longer description explaining *what it does, why, and any
  side effects* when not obvious; `@since`; every `@param` with type and description (hash
  params documented with `@type`); `@return` with type and meaning (`@return void` included).
- **Every property and constant** has a docblock with `@since` and `@var`.
- **Every hook** we fire gets a hook docblock directly above `do_action` / `apply_filters`
  (summary, `@since`, all `@param`s), so it appears in generated docs.
- **Inline comments** explain *why* (constraints, security, performance, core quirks), not what.
- **Templates** start with a docblock listing every `$args` key the template receives, and note
  how a theme overrides it.
- **JavaScript and CSS** follow the WordPress JS/CSS standards; JS uses JSDoc for every function
  (`@param`, `@return`) and a file header comment.
- **Folder structure:** one class per file, grouped by domain under `src/` (see PLAN.md §4).
  Keep `PLAN.md` §4 in sync when adding a folder.

## Commands
- `composer install && npm install` — set up
- `npm run build` — build JS/CSS with esbuild into `assets/dist/`
- `npm run size` — check bundle sizes against the budget
- `npm run env:start` — start `wp-env`
- `npm run test:php` — PHPUnit (runs `composer test` inside wp-env; it reinstalls the tests site DB,
  so e2e specs set up their own plugin/theme state)
- `composer lint` — phpcs + PHPStan; `npm run release` — build the release zip into `dist/`
- `npm run test:e2e` — Playwright
- `composer lint && npm run lint` — all linters

## Definition of done (every task)
1. Acceptance criteria in PLAN.md for that item are met.
2. Tests added/updated and passing; linters clean; size budget passes.
3. Works with JavaScript disabled (graceful fallback to core form POST).
4. Works on a classic theme (Twenty Twenty-One) and a block theme (Twenty Twenty-Five).
5. `CHANGELOG.md` updated. Commit with a clear message.
