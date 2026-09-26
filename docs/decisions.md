# Decisions

Decisions not fully covered by `PLAN.md`. Newest first.

## 2026-09-26 — M4 loading, caching and live updates
- **CSS is inlined right before the first thread** (`<style id="convocept-css">`, 2.6 KB
  gzipped), not lazy-loaded and not in `<head>`. It costs no request, never blocks the content
  above the comments, styles the thread with or without JavaScript, and can't cause the unstyled
  flash / layout shift a lazily fetched stylesheet would when a reader jumps to `#comments`.
  `convocept_inline_css` can empty it for themes that style the thread themselves.
- **JavaScript:** only the inline loader (~440 B gzipped) runs on page load. It injects
  `convocept.js` (plus `convocept_front_modules`) when the thread is within 600px of the
  viewport, on focus/tap/key inside it, or at once for `#comments` / `#respond` / `#comment-N`.
  Configuration is an inert JSON block (`#convocept-config`), hex-escaped against `</script>`.
- **Fragment cache:** rendered comment lists are cached (object cache, else transients, 1 day),
  keyed by core's comment `last_changed` (bumped on any comment or comment-meta change), post
  modified time, page, sort, expanded branch, open/closed, settings, rendering options, locale,
  theme and the comment template file. Filter: `convocept_fragment_cache`.
- **Live updates** (off by default; `live_updates`, `poll_interval` 15–600 s): polls
  `/posts/{id}/updates?after=` only while the tab is visible, with a manual `If-None-Match` so an
  unchanged thread costs one 304. New comments are inserted only where they belong in the
  current view (under their parent; top of page 1 for newest; end of the last page for oldest).
- **No emoji glyphs in our markup:** core's wp-emoji script swaps them for images from s.w.org
  (an external request); icons are inline SVG.
- **Lighthouse (M4 acceptance)**, mobile, Twenty Twenty-Five, 200-comment post vs identical post
  with comments closed: performance 99–100 vs 100, CLS 0, TBT 0, 10 vs 10 requests,
  +8 KB transferred (first 20 comments + inline CSS).

## 2026-09-26 — M3 replies, pagination and links
- **Reply lists** show the first `replies_shown` replies (default 5, 0 = all) with a
  "Show N more replies" link. Without JavaScript the link reloads with `?convocept_expand={root}`,
  which shows that whole branch; with it, `GET /comments/{id}/replies?offset=` appends the rest.
- **Beyond `max_depth`** replies render inside a flat `<ol class="convocept-replies--flat">`
  (no extra indent) instead of as siblings, so every reply list has one shape for JS and CSS.
- **Inline reply** moves the single server-rendered form under the comment (a comment node marks
  its home), so there is never a second copy of the form markup.
- **Load more** replaces the numbered links only on page 1; deeper entry pages keep them.
- **Comment permalinks:** `get_comment_link` is filtered to our page (default sort) plus
  `convocept_expand` for replies. That fixes the no-JS post-submit redirect, wp-admin "View"
  links and emails. `ThreadQuery::position()` loads only top-level IDs (one cached query).
- **Old `#comment-N` links** are followed by the script through `/comments/{id}/context`, with a
  sessionStorage guard against redirect loops; highlighting is pure CSS (`:target`).
- **Benchmark (M3 acceptance):** 2,000-comment post in wp-env (WP_DEBUG on, no persistent object
  cache): first page renders in ~57 ms with 10 queries; full page TTFB 0.20 s vs 1.25 s with
  core's comment template.

## 2026-09-26 — M2 composer and submission decisions
- **Markdown storage:** the raw Markdown is stored in `comment_content` with `&`, `<`, `>`
  entity-encoded, plus `convocept_format = markdown` meta. wp-admin, feeds and other plugins show
  the text exactly as typed, core's kses never strips code samples, and the parser decodes it.
  Only comments from the JS composer are Markdown; no-JS and older comments render through core's
  `comment_text` as before.
- **Parser:** our own (`Markdown\Parser`), no dependency: escape everything, then add only the
  markup we generate; http/https/mailto links only; final `wp_kses` pass with an exact allow-list;
  regex failures fall back to plain text. Markdown links count toward core's `comment_max_links`.
- **REST responses** are JSON with an `html` field rendered by the same PHP templates, so status,
  edit token and count travel with the fragment. Every response is `Cache-Control: no-store`.
- **Session endpoint and nonces:** core REST ignores login cookies without a nonce, so
  `/session` validates the logged-in cookie itself, but only for same-origin requests: our
  `X-Convocept: 1` header (cross-origin pages can't send it: core's CORS preflight doesn't allow
  it), no `Sec-Fetch-Site: cross-site`, matching `Origin` when present, and no `_jsonp`.
- **Rate limits** reject with 429 (5/min and 30/hour per hashed IP for comments; previews 30/min;
  edits 10/min) instead of being a moderation verdict: a real person gets a clear message.
  Fixed windows in the object cache or transients.
- **Moderation pipeline** (`convocept_moderation_checks`): honeypot → spam, timing (< 3 s) → hold,
  Markdown links → hold. It can only tighten core's/Akismet's verdict. It runs for the REST
  composer and for our no-JS form (recognised by its `convocept_form` field); other comment
  sources are untouched. Spam and trash are reported to the browser as "pending".
- **Guest edit tokens:** 32 random characters, HMAC-hashed in `_convocept_edit_token`, kept in
  `localStorage`. Editing is limited to Markdown comments; an author's edit that breaks the
  site's moderation rules sends the comment back to moderation.
- **Registration-required sites:** the form is rendered but `hidden`; the script reveals it for
  logged-in readers, so the cached HTML stays the same for everyone.
- **JS build target** raised to ES2020 (Chrome 91, Firefox 90, Safari 14.1): esbuild can't lower
  destructuring for Safari 13, and those browsers are 2021 vintage.
- **Temporary until M4:** `convocept.js` (3.3 KB gzipped) is enqueued with `defer` on thread
  pages. M4 replaces this with the lazy loader to meet the 0 KB-on-load budget.

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
