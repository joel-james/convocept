# Changelog

All notable changes to Convocept. The format follows [Keep a Changelog](https://keepachangelog.com/),
and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased] — 1.0.0

### Added
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
- Settings: `post_types`, `per_page`, `max_depth`, `default_sort`, `color_scheme`,
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

### Changed
- Migration 1 renames the prototype's `_convocept_edited` comment meta to `convocept_edited_at` and
  drops prototype-only settings (`live_updates`, `poll_interval`, `load_styles`).

### Removed
- The React prototype used to reserve the slug (never released). Until M1 lands, the theme's
  default comments render.
