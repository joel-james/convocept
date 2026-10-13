# Changelog

All notable changes to Convocept. The format follows [Keep a Changelog](https://keepachangelog.com/),
and versions follow [Semantic Versioning](https://semver.org/).

## [1.0.1] — 2026-10-13

### Fixed
- On PHP 7.4 to 8.1, links, URLs and inline code in comments written with the Convocept
  composer showed as a stray number: the Markdown parser's placeholder pattern held a literal
  NUL byte, which PHP before 8.2 rejects ("Null byte in regex", logged as a warning), so the
  placeholders were never swapped back. The stored comments were never affected, and the
  version bump clears the cached thread HTML.

### Changed
- readme.txt links the Convocept website: features, comparisons, migration guides, docs,
  roadmap and support.

### Development
- CI builds the assets before PHPStan and PHPUnit, and the tests accept pretty comment-page
  links and `esc_url()`'s `&#038;` on WordPress 6.5.

## [1.0.0] — 2026-10-13

First release.

### Comment thread
- Server-rendered thread that replaces the theme's comments in classic themes
  (`comments_template`) and block themes (the Comments block and
  core's comment title, list, pagination and form blocks). Comments stay in `wp_comments`.
- Its own design that looks the same in every theme and takes only the theme's font: a card
  at most 720px wide (or the block theme's content width), colour scheme Light, Dark or Follow
  the reader's device, and an accent colour. Theme comment styles are reset inside the thread.
  Themes restyle it through CSS custom properties (`--convocept-*`) or template overrides
  (`{theme}/convocept/*.php`).
- Threaded replies to a set depth (deeper replies flattened with a "Replying to" label), sorting
  by oldest, newest or top, pinned comments first, author badges, lazy avatars with initials.
- Inline replies, "Load more comments", "Show N more replies", and comment links that open the
  right page and highlight the comment, with and without JavaScript.
- Composer with a safe Markdown subset, a formatting toolbar and a server-rendered preview;
  inline errors; an "awaiting moderation" state; an edit window (5 minutes by default) with a
  countdown.
- Upvotes (one per reader) and optional live updates while the tab is visible.
- Moderators approve, pin, edit, spam or trash comments from a ⋯ menu in the thread, and see
  pending comments in place.

### Performance
- No JavaScript and no extra request on page load: the stylesheet (under 4 KB gzipped) is
  inlined next to the thread, and a small inline loader fetches the script (about 6 KB gzipped)
  when the reader nears the comments or interacts with them.
- Page-cache safe: no nonces, names or commenter cookies in the HTML; per-reader state loads
  through the REST API. Rendered comment lists are cached and invalidated automatically.
- `comment-reply.js` is not loaded on posts that use Convocept.

### Subscriptions
- "Email me replies to my comment" and "Email me all new comments on this post" in the form,
  plus subscribing without commenting (a Follow button in the thread, a block and the
  `[convocept_subscribe]` shortcode).
- Double opt-in, one-click unsubscribe (RFC 8058 headers) and a subscription page in the
  thread's design. Notifications are sent in the background in batches, as HTML and plain text,
  in the reader's language; templates can be overridden. Only approved comments on public posts
  are sent.

### Spam protection
- Honeypot, timing check, link limit and per-visitor rate limits, also for the no-JavaScript
  form; works alongside Akismet, Antispam Bee and WordPress's moderation rules. Trusted-proxy
  aware IP detection. Edits are checked again, and an edit that adds a link goes back to
  review. The Comments screen shows why a comment was held.

### Importer
- Disqus importer (Comments → Convocept → Import, Tools → Import and
  `wp convocept import disqus`): dry run, resumable batches, threads matched by identifier or
  URL, replies kept in place, spam and deleted comments skipped, no duplicates on re-runs,
  `.xml.gz` support, 100,000+ comments.

### SEO
- Schema.org `Comment` structured data nested under the article, joining the Yoast SEO and
  Rank Math graphs when those plugins are active. Crawlable comment pages.

### Settings and admin
- Comments → Convocept: General, Appearance, Comment form, Spam & moderation, Advanced, Import
  and Help, styled like core WordPress in your admin colour scheme. Settings → Discussion names
  the options Convocept replaces.

### Privacy, accessibility and translation
- Export and erase personal data cover subscriptions and votes; suggested privacy policy text;
  IP addresses stored in full, anonymized or not at all.
- Deleting the plugin removes its working data; settings, subscriptions and votes are removed
  only when "Delete all Convocept data when the plugin is deleted" is on. Comments are never
  deleted.
- Checked with axe against WCAG 2.2 A/AA in every colour scheme, on classic and block themes,
  and on the settings screen. Keyboard and screen-reader friendly; respects reduced motion.
- Translation ready, with server-side plural forms and right-to-left support.

### Developers
- Actions and filters at every step (see `docs/hooks.md`), a REST API (`convocept/v1`) and
  the `wp convocept import` command. Requires WordPress 6.5 and PHP 7.4.
