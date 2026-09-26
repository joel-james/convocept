# Changelog

All notable changes to Convocept. The format follows [Keep a Changelog](https://keepachangelog.com/),
and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased] — 1.0.0

### Added
- M8 Disqus importer (Tools → Import → "Disqus (Convocept)" and `wp convocept import disqus`):
  dry run with counts and a sample, batched import that resumes after interruptions, threads
  matched by identifier or URL (including moved domains), replies kept in place, spam and deleted
  comments skipped, no duplicates on re-runs (also with comments the Disqus plugin already
  synced), no subscriber emails. Handles 100k+ comment exports and `.xml.gz` files.
- M7 structured data: comments are described as schema.org `Comment` items nested under the
  post's article (replies under their parent), only for the comments the page shows. With Yoast
  SEO or Rank Math the comments join their schema graph instead of adding a second article.
  `structured_data` setting and `convocept_structured_data_*` filters.
- M6 subscriptions: "email me replies" / "email me all new comments" in the composer,
  "subscribe without commenting" (in the thread, `[convocept_subscribe]` shortcode and a block),
  double opt-in, one-click unsubscribe (RFC 8058 headers), a subscription page shown in the
  theme, and a background queue that sends notifications in batches (5,000 subscribers never
  slow down posting). HTML + plain-text emails, overridable templates, sent in the reader's
  language.
- M5 votes and moderation: upvotes (one per reader, remembered across visits, `votes` setting),
  pinned comments that lead page 1, front-end moderator tools (approve, spam, trash, pin, edit
  link) with pending comments shown in place for moderators, and a "Convocept" column on the
  Comments screen explaining why a comment was held or marked spam.
- Clicks on script-only buttons made before the lazy script loads are replayed; readers who start
  typing early are no longer held by the timing check. Moderators skip Convocept's spam checks.
- M4 performance: no Convocept request on page load. The stylesheet is inlined just before the
  thread; a ~440-byte inline loader fetches the script only when the reader nears the comments
  or interacts with them. Rendered comment lists are cached and invalidated automatically.
  Optional live updates (off by default) check for new comments while the tab is visible,
  using ETag/304. Lighthouse on a 200-comment post matches the same post without comments.
- M3 replies and navigation: inline reply (the composer moves under the comment, with Cancel),
  "Load more comments", "Show N more replies" (`replies_shown` setting), and comment links that
  open the right page and highlight the comment, with and without JavaScript. Core's
  `get_comment_link()` now points at Convocept's pages (fixes post-submit redirects and
  wp-admin/email links).
- M2 composer: Markdown (safe subset, own parser) with toolbar and server-rendered preview,
  auto-growing textarea, character counter, REST submission with an instant placeholder,
  inline errors (duplicate, flood, required fields), "awaiting moderation" state, live-region
  announcements, and a count that updates.
- Session endpoint: logged-in readers see "Commenting as …" and skip the guest fields; returning
  guests get their saved name and email filled in, all without per-user data in the page HTML.
- Edit window (default 5 minutes) with a countdown: guests via a hashed token, logged-in authors
  by user ID; edits are re-checked against moderation rules.
- Spam protection: honeypot, timing check, Markdown link limit, per-IP rate limits, trusted-proxy
  aware IP detection, and the `convocept_moderation_checks` pipeline (only ever stricter than
  core/Akismet). Also applied to the no-JavaScript form.
- M1 server-rendered thread: replaces comments in classic themes (`comments_template`) and block
  themes (`core/comments`, `core/post-comments`, `core/post-comments-form`), without running
  core's comment query. Threaded replies to a configurable depth (deeper replies flattened with a
  "Replying to" label), oldest / newest / top sort, crawlable pagination of top-level comments,
  author badge, lazy avatars with an initials fallback, closed and password-protected states.
- No-JavaScript comment form posting to `wp-comments-post.php`, compatible with Akismet and
  Antispam Bee through core's comment form hooks; `replytocom` reply links.
- Theme-overridable templates (`{theme}/convocept/*.php`) and base CSS with design tokens and
  `auto` / `system` / `light` / `dark` color schemes.
- Page-cache safety: no nonces, identity, commenter cookie values or pending comments in the
  HTML; core's unfiltered-HTML nonce is detached from our form.
- `comment-reply.js` is no longer loaded on posts that use Convocept.
- Settings: `replies_shown`, `live_updates`, `poll_interval`, `post_types`, `per_page`, `max_depth`, `default_sort`, `color_scheme`,
  `accent_color`, with sanitizing on read.
- M0 scaffolding: PSR-4 autoloading via Composer, `Plugin::boot()`, activation/deactivation
  handlers, versioned database migration runner with a concurrency lock.
- Settings service reading the single `convocept_settings` option, with a
  `convocept_settings_defaults` filter for add-ons.
- Hooks: `convocept_loaded`, `convocept_activated`, `convocept_deactivated`, `convocept_migrated`,
  `convocept_cron_hooks`.
- Tooling: esbuild front-end build, `@wordpress/scripts` admin build, size-limit budgets
  (loader 1 KB, JS 8 KB, CSS 5 KB gzipped), phpcs (WPCS + PHPCompatibility), PHPStan level 6,
  ESLint, Prettier, stylelint, wp-env, PHPUnit, Playwright, GitHub Actions CI, release zip script.

### Fixed
- "Top" sort ordered wrongly when comments had other metadata, or reused cached "oldest" results.

### Changed
- Migration 1 renames the prototype's `_convocept_edited` comment meta to `convocept_edited_at` and
  drops prototype-only settings (`live_updates`, `poll_interval`, `load_styles`).

### Removed
- The React prototype used to reserve the slug (never released). Until M1 lands, the theme's
  default comments render.
