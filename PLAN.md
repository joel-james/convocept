# Convocept — Build Plan

Version 1.0 plan for Claude Code. Read `CLAUDE.md` first for conventions and the definition of done.

---

## 1. Product summary

**One line:** The fastest comment system for WordPress. Comments stay in your database, load with
zero JavaScript until readers reach them, and help you keep your audience.

**Who it's for:** bloggers, publishers and businesses leaving Disqus (ads, tracking), wpDiscuz
(weight, add-on sprawl) or plain core comments (dated UX, no subscriptions), plus users of the
closed Subscribe to Comments Reloaded plugin who need a maintained replacement.

**Positioning pillars**
1. **Fast** — lighter than hosted widgets like ReplyBox; no framework on the front end.
2. **Native** — standard `wp_comments` storage; uninstall and nothing is lost.
3. **Audience** — every commenter can become a returning subscriber.
4. **Trustworthy** — security-first code, privacy by default, no tracking.

**Business model:** strong free core on WordPress.org; paid add-ons sold from convocept.com
(AI moderation, AI-drafted replies, advanced subscriptions, analytics, etc.). This repo is only the
free core, but it must expose the hooks add-ons need (§6).

---

## 2. Scope (free core)

The scope below is delivered in three releases so real users arrive early. The prototype was only
used to reserve the slug and was never released, so the rebuild is **1.0.0** (see `docs/decisions.md`).

**Release 1.0.0 — "Replace core comments" (ReplyBox parity, faster)**
M0–M4, M5 without emoji reactions (upvotes, pinning, moderator tools), M6 subscriptions,
M7 structured data, M8 **Disqus importer only**, M9 lite settings (no live preview/onboarding),
M11, M12.

**Release 1.1.0 — "Migrate from anything, see what works"**
M8 remaining importers (Subscribe to Comments Reloaded, wpDiscuz votes/subscriptions), onboarding
checklist with detected-import prompts, M13 Insights (free comment statistics dashboard),
M14 Comments screen (moderation inside the Convocept app).

**Release 1.2.0 — "Delight"**
Emoji reactions, optional visible-tab polling, Abilities API (M10), settings live preview,
basic AI spam/toxicity check through the core WordPress AI Client using the site owner's own
provider (feature-detected, opt-in, disclosed in readme). Advanced AI stays in paid add-ons.

**Free vs paid rule:** free core is complete for any single site; paid add-ons sell saving time at
scale (AI moderation rules and dashboard, AI-drafted replies, summaries, digests, detailed
analytics, social login, webhooks). Free Insights is complete on its own: no locked panels or
"upgrade to see" teasers.

**In scope (all releases above)**
- Server-rendered comment thread replacing the theme's comment output (classic + block themes)
- Modern composer: guest and logged-in commenting, Markdown (safe subset), preview, edit window
- Threaded replies, sort (oldest / newest / top), "load more" pagination
- Lazy loading: no JS/CSS for interaction until the thread nears the viewport
- Reactions (one small fixed set) and optional upvotes
- Pinned comments, author badge
- Comment subscriptions: reply notifications, per-post subscriptions, double opt-in,
  one-click unsubscribe, subscriber management page
- Moderation integrated with core Comments screen + small front-end moderator tools (1.0);
  our own Comments screen in the admin app, beside the core one (1.1, M14)
- Spam protection: core checks + Akismet passthrough + honeypot + timing check + rate limits
- Structured data for comments
- Importers: Disqus XML (1.0), Subscribe to Comments Reloaded and wpDiscuz votes/subscriptions (1.1).
  Importers live in core but load only in wp-admin and WP-CLI (never on front-end requests).
- Abilities API registration (when available)
- Settings screen (React in wp-admin)
- Insights: comment statistics dashboard built only from data already stored (1.1)
- i18n, accessibility (WCAG 2.2 AA), privacy exporters/erasers, clean uninstall

**Out of scope for the free core (future paid add-ons — only leave hooks)**
AI moderation, AI-drafted replies, thread summaries, digests and newsletter sync, social/passkey
login, verified-human badges, media uploads, detailed analytics (view-based engagement, funnels,
comparisons, exports, email reports), WooCommerce Q&A, ActivityPub
display polish, live updates via push.

---

## 3. Tech stack and tooling

| Area | Choice |
|---|---|
| PHP | 7.4–8.4, PSR-4 via Composer (classmap-authoritative build for release) |
| Front-end JS | Vanilla ES2019 modules bundled by **esbuild** into one lazy-loaded file |
| Admin JS | React via `@wordpress/element` / `@wordpress/components`, built with `@wordpress/scripts` |
| CSS | Hand-written, CSS custom properties, scoped under `.convocept`; built/minified by esbuild or lightningcss |
| Local env | `@wordpress/env` |
| PHP tests | PHPUnit + WP test suite (via wp-env) |
| E2E tests | Playwright (`@wordpress/e2e-test-utils-playwright`) |
| Lint | phpcs (WPCS), PHPStan level 6 (+ szepeviktor/phpstan-wordpress), ESLint, Prettier, stylelint |
| Size budget | `size-limit` (gzipped) — fails CI when over budget |
| CI | GitHub Actions: lint → unit → e2e → size → build zip |
| Release | `npm run release` builds a clean zip (no dev files) and a WordPress.org SVN-ready folder |

---

## 4. Repository structure

```
convocept/
├── convocept.php                 # bootstrap: header, constants, autoloader, Plugin::boot()
├── uninstall.php
├── readme.txt                    # WordPress.org readme
├── README.md / CHANGELOG.md / LICENSE
├── composer.json / package.json  # PHP and JS dev tooling
├── src/                          # PHP, PSR-4 namespace Convocept\ (one class per file)
│   ├── Plugin.php                # wiring: creates services, registers hooks per context
│   ├── Lifecycle.php             # activation / deactivation
│   ├── Settings/                 # option schema, defaults, sanitizing getters
│   ├── Database/                 # migration runner, Migrations/, table schemas
│   ├── Comments/                 # domain: queries (ThreadQuery, Thread), Sort, submission,
│   │                             #   editing, pinning, scores
│   ├── Render/                   # HTML output: ThreadRenderer, TemplateLoader, formatters
│   ├── Integration/              # hooks into themes and other plugins
│   │   ├── ClassicTheme.php      #   comments_template replacement
│   │   ├── BlockTheme.php        #   core/comments block replacement
│   │   └── …                     #   Akismet, Jetpack, WPML, ActivityPub, SEO plugins
│   ├── Assets/                   # front-end/admin asset registration and the inline loader
│   ├── Rest/                     # Controller + one class per endpoint group
│   ├── Markdown/                 # safe Markdown → HTML (server side only)
│   ├── Votes/                    # upvotes (1.0), reactions (1.2)
│   ├── Subscriptions/            # model, mailer, queue, manage page, tokens
│   ├── Security/                 # IpResolver, RateLimiter, FormToken
│   ├── Moderation/               # Pipeline, Decision, Context, Checks/ (honeypot, timing, links)
│   ├── Schema/                   # structured data output
│   ├── Import/                   # Importer interface + Disqus, StCR, wpDiscuz (admin/CLI only)
│   ├── Insights/                 # stats rollup + Insights REST data (1.1)
│   ├── Abilities/                # Abilities API registration (feature-detected)
│   ├── Privacy/                  # personal data exporters/erasers, policy text
│   ├── Admin/                    # menus, settings/insights screen loaders, notices
│   └── Cli/                      # WP-CLI commands
├── templates/                    # theme-overridable: copy to {theme}/convocept/{file}
│   ├── thread.php                #   wrapper: header, composer, list, pagination
│   ├── thread-header.php         #   count + sort navigation
│   ├── comment.php               #   one comment (recursive for replies)
│   ├── composer.php              #   the comment form (works without JS)
│   ├── pagination.php
│   ├── subscription-manage.php
│   └── emails/
├── assets/
│   ├── src/front/                # vanilla JS modules + CSS
│   ├── src/admin/                # React settings / Insights apps; ui/ = Foxe Plugin Admin UI components
│   └── dist/                     # build output (not committed)
├── languages/
├── docs/                         # decisions.md, hooks.md
├── bin/                          # build and release scripts
└── tests/
    ├── php/                      # PHPUnit, mirrors src/ folders
    ├── e2e/specs/                # Playwright
    └── phpstan/
```

---

## 5. Architecture

### 5.1 Rendering (server first)
- `ThreadRenderer` queries top-level comments (paged, sorted) plus their replies to a configurable
  depth, then renders `thread.php` → `comment.php` (recursive) → `composer.php`.
- **Classic themes** (`Integration\ClassicTheme`): filter `comments_template` to return our
  wrapper, and short-circuit core's own comment query inside `comments_template()` (via
  `comments_template_query_args` + `comments_pre_query`) so comments are never queried twice.
- **Block themes** (`Integration\BlockTheme`): `pre_render_block` for `core/comments`, legacy
  `core/post-comments` and a standalone `core/post-comments-form` outputs our thread instead, so
  core's inner blocks never run their queries. The thread renders once per post per request;
  later matching blocks output nothing. A `convocept/thread` block for manual placement follows.
- Output must contain **no per-user data**: no nonces, no "logged in as", no commenter-cookie
  values, no moderation links. These are hydrated after the session call (§5.3).
- The composer is a real `<form action="wp-comments-post.php" method="post">` so it works with JS
  disabled; JS upgrades it.
- Templates are overridable by copying to `{theme}/convocept/{template}.php`
  (`TemplateLoader` checks child theme, parent theme, then plugin).
- Comment HTML is cached per comment in a transient/object cache keyed by comment ID + modified
  time + settings hash; invalidate on edit/status change/reaction change.

### 5.2 Front-end JS (vanilla)
- A tiny inline loader (< 1 KB, inline in the thread wrapper) uses `IntersectionObserver`
  (rootMargin ~600px) **or** focus/click on the composer to inject `convocept.js` and `convocept.css` once.
- `convocept.js` modules: `session`, `composer`, `reply`, `pagination`, `reactions`, `subscribe`,
  `moderate`, `edit`, `a11y` (live-region announcements). One delegated listener on `.convocept` root.
- Network: `fetch` to REST; responses are **HTML fragments** (`text/html`) inserted with
  `insertAdjacentHTML` or `<template>` cloning. JSON only for tiny state (reaction counts, errors).
- Optimistic UI: show the submitted comment immediately with a "Posting…" state, replace with the
  server fragment, roll back on error. If held for moderation, show "awaiting moderation" version.
- Guest name/email/url prefill: read core's `comment_author_*` cookies client-side.
- No polling by default. Optional setting: poll for new comments only while tab is visible,
  every 60s, with `ETag`/`If-None-Match` so unchanged checks return 304.
- Replace core `comment-reply.js` (dequeue it when our thread renders).

### 5.3 REST API (`/wp-json/convocept/v1/…`)

| Route | Method | Auth | Returns |
|---|---|---|---|
| `/session?post_id=` | GET | public (cookie-aware) | JSON: nonce, user info, can_moderate, commenter prefill flags. `Cache-Control: no-store` |
| `/posts/{id}/comments?page=&sort=&parent=` | GET | public | HTML fragment of comments + pagination meta headers |
| `/posts/{id}/comments` | POST | public + nonce for logged-in | HTML fragment of new comment (or error JSON) |
| `/comments/{id}` | PATCH | author within edit window, or moderator | HTML fragment |
| `/comments/{id}` | GET | author within edit window, or moderator | JSON: Markdown source for editing |
| `/preview` | POST | public, rate-limited | JSON: rendered Markdown HTML |
| `/comments/{id}/reactions` | POST | public, rate-limited | JSON counts |
| `/comments/{id}/moderate` | POST | `moderate_comments` | HTML fragment / status |
| `/comments/{id}/pin` | POST | `moderate_comments` | status |
| `/subscriptions` | POST | public, rate-limited | JSON (always generic "check your email") |
| `/subscriptions/confirm` & `/unsubscribe` | GET (tokenized links) | token | redirect to manage page |

- Fragments are returned inside JSON (`{ html, status, … }`) so metadata travels with them
  (see `docs/decisions.md`); the HTML always comes from the PHP templates.
- Comment creation must go through **`wp_handle_comment_submission()`** so core flood checks,
  disallowed keys, `pre_comment_approved`, Akismet and other anti-spam plugins all still apply.
- Every route: `permission_callback`, strict `args` schemas, sanitization, consistent error shape.

### 5.4 Data model
Use core tables wherever possible.

**`wp_commentmeta` keys**
- `convocept_format` — `markdown` for comments written in our composer (so legacy/imported comments
  keep core rendering). Markdown itself lives in `comment_content` (§5.5).
- `convocept_pinned` (1), `convocept_edited_at` (timestamp), `convocept_reactions` (JSON counts cache),
  `convocept_score` (int, for "top" sort)

**Custom tables** (created with `dbDelta`, versioned migrations)
- `{prefix}convocept_subscriptions`: id, email_hash, email, post_id (0 = all-site), comment_id (reply
  subscription), status (pending|active|unsubscribed), token_key (random secret for signed links;
  see docs/decisions.md), created_at, confirmed_at, source (native|import_stcr|import_wpdiscuz),
  locale. Unique (email_hash, post_id, comment_id); indexes (post_id,status), (comment_id,status).
- `{prefix}convocept_reactions`: id, comment_id, reaction, voter_hash (user ID or salted IP+UA hash),
  created_at. Unique (comment_id, voter_hash, reaction).
- `{prefix}convocept_stats_daily` (M13): day (DATE), post_id (0 = site total), metric (varchar 64),
  value (bigint). Primary key (day, post_id, metric); index (metric, day). Filled by a nightly
  rollup; the Insights screen reads only this table, never scans `wp_comments`. Add-ons may write
  their own metric names.

### 5.5 Markdown decision
Store the raw Markdown in `comment_content` (keeps core compatibility: admin screens, exports,
other plugins see readable text). Render on output through a **safe subset** parser (bold, italic,
links with `rel="nofollow ugc noopener"`, inline code, code blocks, blockquotes, lists). Raw HTML is
escaped, never passed through. Cache rendered HTML (§5.1). Pick a small, maintained PHP parser
(e.g. Parsedown with safe mode) or write a minimal one; add fuzz-style tests for XSS.

### 5.6 Caching & compatibility
- Fully compatible with page caches (WP Rocket, LiteSpeed, W3TC, Cloudflare APO): nothing
  per-user in HTML; new comments invalidate the post's page cache via `clean_post_cache` and the
  standard `comment_post` / `wp_set_comment_status` hooks that cache plugins already listen to.
- Works with object cache when present; degrades to transients.
- Don't break: Akismet, Antispam Bee, Jetpack (disable Jetpack Comments conflict with a notice),
  WPML/Polylang (locale on subscriptions), ActivityPub plugin (render federated comments normally).

---

## 6. Extensibility for paid add-ons

Add-ons are separate plugins that require Convocept. The core must never contain license
checks or locked features. Provide:

- **Lifecycle hooks:** `convocept_before_submit` (filter, can reject with WP_Error), `convocept_comment_created`,
  `convocept_comment_status_changed`, `convocept_comment_edited`, `convocept_reaction_added`, `convocept_subscription_confirmed`.
- **Moderation pipeline:** `convocept_moderation_checks` filter — an ordered list of checker callables
  returning `approve | hold | spam | trash` with a reason. Core ships honeypot, timing and rate
  limit checkers; the AI moderation add-on appends its own. Store the decision + reason in
  `convocept_moderation_log` comment meta and show it in the admin comment row.
- **Render slots:** `convocept_render_comment_actions`, `convocept_render_comment_footer`,
  `convocept_render_composer_after`, `convocept_render_thread_header` (for summaries), `convocept_render_badges`.
- **Insights:** `convocept_stats_metrics` filter (metrics the nightly rollup computes) and
  `convocept_insights_panels` + `window.convoceptAdmin.registerInsightsPanel()` so the analytics
  add-on adds charts to the same screen.
- **Settings registry:** add-ons register tabs/fields in the React settings app via
  `convocept_settings_schema` filter + a JS registry (`window.convoceptAdmin.registerPanel()`).
- **REST:** add-ons register routes under `convocept/v1/{addon}`.
- **Email:** `convocept_mailer` filter to swap the transport; templates overridable.
- **Asset loading:** `convocept_front_modules` filter so add-ons append a lazily-loaded module to the same
  loader instead of loading their own scripts on page load.
- **Abilities:** add-ons register additional abilities under the `convocept/` namespace.
- Document all hooks in `docs/hooks.md` as they're added.

---

## 7. Security requirements (learned from wpDiscuz's 2025–26 CVEs)

- Escape on output with the right function (`esc_html`, `esc_attr`, `esc_url`, `wp_kses` with an
  explicit allow-list). Guest fields (name, website) are untrusted everywhere, including emails
  and admin screens.
- Never call `do_shortcode()`, `apply_filters('the_content')` or `eval`-like paths on user content,
  including in notification emails.
- `IpResolver`: use `REMOTE_ADDR` by default; honour proxy headers only when the site owner sets
  trusted proxy CIDRs (Cloudflare preset available).
- Rate limits (per IP hash and per email): comment submit, reactions, subscription requests,
  resend-confirmation. Use object cache/transients with sliding windows.
- Subscription endpoints never reveal whether an email is subscribed (same response always).
- Tokens: random 32-byte, store only the hash, expire confirmation tokens after 48 h.
- Edit window for guests is tied to a random token issued at submit time (kept client-side in
  localStorage, stored hashed in comment meta), never to name/email matching.
- Capability checks on every privileged route; nonces for logged-in cookie auth.
- Settings import/export (if added) must validate against the schema, never store raw HTML.
- Run WPScan/Plugin Check (`plugin-check` plugin) in CI before each release.

---

## 8. Milestones

Each milestone ends in a working, tested, committed state. Do not start the next one until the
current definition of done (CLAUDE.md) is met.

### Pre-M0 — Slug reservation ✅ done (slug `convocept` approved, September 2026)
- The approved prototype (React front end, `includes/` + JS `src/`) is the first git commit. It was
  never released. M0 retires it in favour of the §4 structure. Its files and its data migration
  were deleted after M8.

Milestones M0–M7, the Disqus part of M8, M9 lite, M11 and M12 make up release 1.0.0 (§2).

### M0 — Scaffolding & tooling ✅ done
- Plugin bootstrap, autoloading, `Plugin::boot()`, activation/deactivation, DB migrations runner.
- wp-env, PHPUnit, Playwright, phpcs, PHPStan, ESLint, Prettier, stylelint, size-limit, esbuild,
  `@wordpress/scripts` for admin, GitHub Actions workflow, release script.
- **Accept:** plugin activates cleanly on WP 6.5 / PHP 7.4 and latest WP / PHP 8.4; CI green;
  `npm run release` produces a zip that passes the Plugin Check plugin.

### M1 — Server-rendered thread ✅ done
- `ThreadRenderer`, `TemplateLoader`, templates, classic + block theme integration.
- Threading (depth setting, default 3 on mobile-friendly indent), author badge, relative dates with
  `<time datetime>`, avatars (`loading="lazy"`, fixed dimensions, initials fallback).
- Sort: oldest / newest / top (top uses `convocept_score`, fallback oldest). Server pagination.
- Comment count, "comments are closed" and password-protected post states.
- Base CSS with design tokens (CSS variables) and light/dark support via `prefers-color-scheme`
  + a `data-convocept-theme` override; inherits theme font by default.
- **Accept:** renders identically with JS disabled; no per-user data in HTML (unit test asserts
  no nonce/cookie values in output); CLS = 0 in Lighthouse; theme override works.

### M2 — Composer & submission ✅ done
- Composer template (textarea with auto-grow, name/email/url for guests, consent checkbox if
  enabled, subscribe checkbox), Markdown toolbar (minimal), preview tab (server-rendered via
  REST), character counter.
- JS upgrade of the form: REST POST, optimistic insert, error states, retry, "awaiting
  moderation" state, focus management, live-region announcements.
- Submission through `wp_handle_comment_submission()`; honeypot + minimum-time check;
  rate limiting; `convocept_before_submit` filter; moderation pipeline (§6).
- Session endpoint + lazy nonce; logged-in identity rendering after session loads.
- Guest edit window (default 5 min) with signed token.
- **Accept:** no-JS post still works via core; duplicate/flood errors shown inline; Akismet marks
  spam correctly in e2e (mock); XSS test suite passes for all fields.

### M3 — Replies, pagination, load more ✅ done (2,000 comments: first page ~57 ms, 10 queries)
- Inline reply form (moved `<template>`), cancel, nested insert.
- "Load more" top-level pages and "show N more replies" per branch, as HTML fragments.
- Deep-link to `#comment-123` loads the page containing it and highlights it.
- **Accept:** 2,000-comment fixture renders first page < 150 ms server time with object cache;
  deep links work with and without JS.

### M4 — Lazy loading & performance hardening ✅ done (Lighthouse 99–100 vs 100 baseline, 0 requests before scroll)
- Inline loader (< 1 KB) with IntersectionObserver + interaction triggers; loads `convocept.js`/`convocept.css`
  once; `convocept_front_modules` hook.
- Dequeue `comment-reply.js` when thread renders; no jQuery dependency anywhere.
- Rendered-comment HTML cache + invalidation.
- Optional visible-tab polling with ETag.
- **Accept:** size-limit budgets pass; Lighthouse performance on a test post with 200 comments is
  equal to the same post with comments disabled (±2 points); zero requests before scroll.

### M5 — Reactions, votes, pinning, moderation tools (emoji reactions move to 1.2.0) ✅ done (upvotes, pins, moderator tools, admin column)
- Fixed reaction set (e.g. 👍 ❤️ 😂 🤔), configurable on/off; optional upvote mode instead.
- `convocept_reactions` table, counts cached in meta, `convocept_score` recalculated.
- Pin/unpin (moderators), front-end moderator menu (approve, spam, trash, pin) after session shows
  capability.
- Admin: moderation-decision reason column in core Comments list.
- **Accept:** one reaction per voter per type; rate limits enforced; moderator actions update the
  fragment in place.

### M6 — Subscriptions (StCR replacement) ✅ done (5,000-subscriber batch test; e2e subscribe → confirm → notify → unsubscribe)
- Options on composer: "notify me of replies to my comment" / "notify me of all new comments".
  Standalone "subscribe without commenting" form (block + shortcode).
- Double opt-in (configurable), one-click unsubscribe (RFC 8058 `List-Unsubscribe` +
  `List-Unsubscribe-Post` headers), manage page (virtual page rendered in theme, `noindex`,
  token-authenticated, no email in URL).
- Notification queue: on approve, enqueue; WP-Cron batches (default 50 per run); use Action
  Scheduler automatically if already installed. Author notification for new comments.
- Emails: plain, fast HTML + text alternative; templates overridable; `convocept_mailer` filter.
- **Accept:** e2e covers subscribe → confirm → receive → unsubscribe; no email ever appears in a
  URL; sending 5,000 notifications doesn't time out a request.

### M7 — Structured data & SEO ✅ done (schema.org validator: 0 errors, 0 warnings)
- Output schema.org `Comment` items nested under the post's `Article`/`BlogPosting` (JSON-LD),
  including author name, date, text, `upvoteCount` when votes enabled. Check Google's current
  structured data documentation (DiscussionForumPosting vs Comment) and follow it.
- Ensure comments are in the server HTML (indexable); pagination uses crawlable links.
- Coordinate with Yoast/Rank Math schema graphs (append to their graph via their filters when
  active instead of duplicating).
- **Accept:** Rich Results Test / Schema validator show no errors on a fixture post.

### M8 — Importers (Disqus in 1.0.0; StCR and wpDiscuz in 1.1.0) — Disqus ✅ done (100k comments via WP-CLI; StCR and wpDiscuz pending for 1.1)
Importer code lives in `src/Import/` and is wired only for wp-admin and WP-CLI; a test asserts no
`Convocept\Import\*` class loads on a front-end request. Common `Importer` interface: detect → dry run (counts + sample) → batched import with resume →
report. Idempotent (store source IDs in meta to avoid duplicates). Run from admin with progress UI
and via WP-CLI (`wp convocept import <source>`).
- **Subscribe to Comments Reloaded:** read its stored subscriptions (inspect the plugin's source
  in the `stcr/subscribe-to-comments-reloaded` GitHub repo to confirm the current storage format —
  historically post meta keys like `_stcr@_{email}` plus a subscribers table). Map statuses,
  keep confirmed status (no re-opt-in needed unless the owner chooses).
- **Disqus XML export:** parse threads/posts streaming (XMLReader, not DOM), map to posts by URL /
  identifier, recreate parent relationships, skip spam/deleted, set `comment_agent` =
  `Disqus import`. Handle 100k+ comments.
- **wpDiscuz:** comments are already native; import votes and subscriptions from its tables/meta
  (verify schema against current wpDiscuz source before writing the mapper).
- **Accept:** each importer tested against fixture exports; re-running produces no duplicates;
  large Disqus fixture imports without timeouts.

### M9 — Admin settings app (1.0.0 ships tabs + persistence; live preview and onboarding in 1.1/1.2) — 1.0 part ✅ done (Foxe Plugin Admin UI; sidebar pages instead of tabs)
- React app under **Settings → Convocept** (or top-level "Comments → Convocept"):
  tabs General, Appearance, Composer, Moderation & Spam, Subscriptions, Import, Advanced.
- Live preview of the thread with current appearance settings.
- Settings stored in one option (`convocept_settings`), schema-validated via REST settings route.
- Onboarding: first-run checklist (appearance, subscriptions, import from X detected).
- **Accept:** all settings persist and apply without page cache issues; admin bundle only loads on
  its own screen.

### M10 — Abilities API & agent readiness (1.2.0)
- Feature-detect `wp_register_ability` (WP 6.9+). Register abilities: `convocept/list-pending`,
  `convocept/moderate`, `convocept/reply`, `convocept/summarize-thread-data`
  (returns structured thread data; summarizing itself is for AI add-ons/clients).
- Correct permission callbacks mirroring REST capabilities; input/output JSON schemas.
- **Accept:** abilities appear when available and are absent (no errors) on older WP.

### M11 — Accessibility, i18n, privacy, uninstall ✅ done (axe: 0 serious/critical issues in every scheme, both themes, settings screen)
- WCAG 2.2 AA: keyboard flow, visible focus, labelled controls, live regions, reduced motion,
  colour contrast in both schemes. Test with axe in Playwright.
- `.pot` generation, RTL CSS, date/number localization.
- Privacy: personal data exporter/eraser for subscriptions and reactions; suggested privacy
  policy text; option to hash/retain IPs per policy.
- Uninstall: keep comments (core data); remove plugin tables/options only if the owner ticked
  "delete all Convocept data on uninstall".
- **Accept:** axe reports zero serious issues; exporter/eraser tested; uninstall behaves as set.

### M12 — Release preparation ✅ done except the release itself (version bump to 1.0.0, real logo for icon/banner)
- `readme.txt`: title "Convocept – Fast Native Comment System" (keep "Convocept" first;
  no third-party trademarks such as Disqus in the name, put those in tags and description), short description with key terms, FAQ (migrations, caching, speed, privacy),
  screenshots, changelog. Tags: comments, comment system, disqus, subscribe to comments, spam.
- Plugin assets: icon, banner, screenshots (light + dark).
- Docs pages for convocept.com: install, migrate from Disqus / wpDiscuz / StCR, theming,
  hooks reference, performance benchmarks (publish the size budget and methodology).
- Final Plugin Check, PHP compatibility check, security review pass.

### M13 — Insights dashboard (1.1.0, free)
Admin screen **Comments → Insights** (React, own bundle, loads only on that screen). Built only from
data already stored: no front-end tracking, no extra requests, no page-view counts.
- Comments over time (7 / 30 / 90 days): approved vs pending vs spam.
- Top posts by comments, top commenters, new vs returning commenters.
- Average time to first reply (author/moderator), unanswered comments count.
- Subscriptions: new, unsubscribed, confirmation rate.
- Most-upvoted comments.
- `convocept_stats_daily` rollup: nightly WP-Cron job aggregates the previous day (batched,
  resumable); first run backfills history in batches. Live "today" numbers from cheap indexed
  queries, cached 10 minutes.
- REST: `GET /convocept/v1/insights?range=` (`moderate_comments`), JSON.
- Hooks in §6 so the paid analytics add-on extends the same screen.
- **Accept:** dashboard loads < 300 ms server time on a 100k-comment fixture; rollup is idempotent
  (re-running a day gives the same numbers); zero front-end bytes or requests added; numbers match
  direct SQL counts in tests.

Paid analytics add-on (later, separate plugin): view-based engagement (comments per 1,000 views,
scroll-to-thread rate, composer abandonment) through an opt-in, cookieless `sendBeacon` sent only
after the thread has loaded (never on page load), or by reading views from an existing analytics
plugin; commenter to subscriber funnel and retention; moderation stats; per author/category/period
comparisons; CSV export; weekly email reports.

### M14 — Comments screen (1.1.0)
Moderation inside the Convocept admin app, built with `@wordpress/dataviews` (bundled in our admin
build: core does not ship it as a script handle). It sits **beside** the core Comments screen,
never instead of it: Akismet, anti-spam plugins, WooCommerce and others add columns, row actions,
bulk actions and filters to `edit-comments.php`, and core links (admin bar bubble, Activity
widget, moderation emails, the post editor) all point there.
- Page **Comments** in the app: list with status views (all / pending / approved / spam / trash),
  search, filters (post, author, date), sorting, pagination, saved view per user.
- Row and bulk actions: approve, unapprove, spam, not spam, trash, restore, delete permanently
  (with confirmation). Inline reply and edit. Thread context (parent and replies) in a side panel.
- Convocept fields: votes, pinned, subscribed. Pin/unpin from the list.
- Data from core `/wp/v2/comments` (context `edit`) plus our fields registered with
  `register_rest_field`; no second comments API. Capabilities as core (`moderate_comments`,
  `edit_comment`).
- "Open in Convocept" link on the core Comments screen; core screen otherwise untouched.
- Menu: the app moves from Settings → Convocept to a **Comments → Convocept** submenu (old URL
  redirects), next to Comments → Insights (M13).
- Later, opt-in only: setting "Use Convocept for the Comments menu" that points the core menu
  item at our page while `edit-comments.php` stays reachable. Off by default (WordPress.org
  admin-hijacking rule).
- Hooks: `convocept_comments_fields` (JS filter/registry for extra columns), row actions registry,
  so add-ons (AI moderation) extend the same screen.
- **Accept:** list of 100k comments pages in < 300 ms server time; every action matches core
  results (counts, hooks such as `transition_comment_status` fire); Akismet spam/ham reported the
  same as from the core screen; keyboard and screen-reader usable; bundle loads only on its screen.

---

## 9. Testing matrix
- WordPress: 6.5, latest, and nightly. PHP: 7.4, 8.1, 8.3, 8.4.
- Themes: Twenty Twenty-One (classic), Twenty Twenty-Five (block), Astra, GeneratePress, Kadence.
- Caching: WP Rocket-style full-page cache simulation (serve cached HTML, verify commenting works),
  LiteSpeed Cache, object cache (Redis) on/off.
- Plugins: Akismet, Antispam Bee, Yoast SEO, Rank Math, WPML/Polylang, ActivityPub, Jetpack.
- Browsers: latest Chrome, Firefox, Safari (iOS), Chrome Android; JS disabled.

## 10. Performance acceptance (publishable benchmark)
Fixture post, 200 comments, Twenty Twenty-Five, no page cache vs. comments disabled:
- Transferred JS before scroll: 0 KB. After thread visible: ≤ 8 KB gzipped.
- CSS: ≤ 5 KB gzipped, inlined immediately before the thread (no request, not render-blocking;
  see `docs/decisions.md`).
- Lighthouse Performance within 2 points of the no-comments baseline; CLS 0; INP < 100 ms on
  mid-range mobile emulation for reply/submit.
- Server render of first page of comments < 50 ms with object cache.

## 11. Working agreement for Claude Code
- Follow milestones in order; open each by listing the tasks you'll do, end by running all checks.
- Keep PRs/commits small and focused; one feature per commit where practical.
- When a decision isn't covered here, choose the simplest option that keeps the performance
  budget and security rules, note it in `docs/decisions.md`, and continue.
- Ask before: adding runtime dependencies, changing the data model, changing REST contracts,
  or anything that would affect WordPress.org guideline compliance.
- Verify third-party formats (StCR, wpDiscuz, Disqus) against their actual source/exports; do not
  guess schemas.
