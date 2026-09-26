# Decisions

Decisions not fully covered by `PLAN.md`. Newest first.

## 2026-09-26 — M1 rendering decisions
- **Color schemes:** `auto` (default) inherits the theme's text and background and mixes tints
  from `currentColor`, so the thread matches light and dark themes without a jarring box.
  `system` follows the visitor's OS (`prefers-color-scheme`); `light` / `dark` force a palette.
- **Stylesheet:** enqueued with a `path`, so core inlines it (it is under core's 20 KB inline
  limit) and the page makes no extra request. M4 revisits this with the lazy loader.
- **Pingbacks/trackbacks** are excluded from the thread (`convocept_excluded_comment_types`).
- **Replies** are always chronological, whatever the top-level sort. Beyond `max_depth` they
  are flattened at the last level with a "Replying to {name}" label.
- **Pagination** counts top-level comments only and uses core's `comment-page-N` / `cpage` URLs;
  the sort lives in `?convocept_sort=` and is omitted for the default sort (one canonical URL).
- **Per-user output:** core's `wp_comment_form_unfiltered_html_nonce` is detached while our form
  renders, because it prints a nonce for admins. Only approved comments are ever rendered.
- **Anti-spam compatibility:** the form fires core's `comment_form_*` hooks and the
  `comment_form_field_comment` filter so Akismet and Antispam Bee work unchanged.
- **Classic themes:** the wrapper gets `comments-area` plus known per-theme width classes
  (`Integration\ClassicTheme::THEME_CLASSES`), filterable via `convocept_thread_classes`.
- **Guest fields** are not `required` in HTML: a logged-in reader without JavaScript still sees
  them, and core validates server-side. The M2 script hides them for logged-in readers.

## 2026-09-26 — Insights free, detailed analytics paid
Free core gets an Insights dashboard (1.1.0) built only from data already stored: comment counts,
top posts and commenters, time to first reply, subscription and vote stats. It adds no front-end
tracking, bytes or requests. Anything needing page views (engagement rate, funnels) belongs to the
paid analytics add-on, opt-in and cookieless. Numbers come from a new `convocept_stats_daily`
rollup table (approved by the owner) so large sites never scan `wp_comments` on dashboard load.

## 2026-09-26 — Composer autoloader ships in the release zip
PSR-4 autoloading comes from Composer. The release script runs
`composer install --no-dev --classmap-authoritative`, so the zip carries only `vendor/autoload.php`
and the generated classmap (no third-party runtime code). If `vendor/autoload.php` is missing
(e.g. a fresh git checkout without `composer install`), the plugin shows an admin notice and stays
inert instead of fataling.

## 2026-09-26 — Prototype data migration
The prototype stored settings in `convocept_settings` and a "last edited" timestamp in the private
comment meta key `_convocept_edited`. The option name is kept. Migration `1` renames the meta key to
`convocept_edited_at` (the name in PLAN.md §5.4) and drops prototype-only settings
(`live_updates`, `poll_interval`, `load_styles`) while keeping `enabled` and `edit_window`. The prototype was never released, so this only
covers dev and test installs; it is cheap and harmless elsewhere.

## 2026-09-26 — Rebuild ships as 1.0.0
The approved prototype only reserved the slug and was never released, so the rebuild is the first
real release: 1.0.0 (core + Disqus import), 1.1.0 (remaining importers, onboarding, Insights),
1.2.0 (reactions, polling, Abilities, basic AI). Development builds use `1.0.0-dev`.

## 2026-09-26 — Disqus importer in core, not a separate add-on
Importer code has zero front-end cost (autoloaded, wired only in wp-admin and WP-CLI), adds no
dependencies (XMLReader is built in), and lets onboarding offer the import at the moment a site
owner is deciding to switch. A separate plugin would need its own WordPress.org review and
release cycle. Guard: a test asserts no `Convocept\Import\*` class loads on a front-end request.

## 2026-09-26 — Basic AI checks free (owner's own provider), advanced AI paid
Free core (1.2.0) may run a basic spam/toxicity check through the core WordPress AI Client when the
site owner has configured a provider. It is opt-in, feature-detected and disclosed in the readme
(external service). Paid add-ons sell advanced AI: custom rules, moderation dashboard,
AI-drafted replies, summaries, and hosted AI that needs no key.
Needs owner confirmation before implementation (WordPress.org guideline impact).
