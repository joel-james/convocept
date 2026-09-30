# Changelog

All notable changes to Convocept. The format follows [Keep a Changelog](https://keepachangelog.com/),
and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased] — 1.0.0

### Added
- Settings → Discussion shows a notice naming the post types Convocept handles and the options
  it replaces there (threading, comment pages and comment order; pingbacks and trackbacks are
  not shown), with a link to Convocept's settings. Every other Discussion option still applies,
  and none are hidden: they still apply to other post types and when Convocept is off.
- Comments → Convocept is rebuilt on the new Foxe Plugin Admin Kit and looks like core
  WordPress: your admin colour scheme instead of black, WordPress's grey background, bordered
  cards in one left-aligned column. Save sits in the sticky page header with the count of unsaved
  changes and Discard beside it, instead of a floating bar (which overflowed on phones). The
  sidebar stays in view while scrolling, shows whether Convocept is on, and counts comments
  waiting for review. Composer and Subscriptions are merged into **Comment form**; **Spam &
  moderation** lists the active protections with ticks. Number fields show their unit inside
  (25 comments, 30 seconds). The accent colour opens a picker with your theme's colours and a few
  suggested ones, and Appearance shows a preview comment. Import shows its four steps and the dry
  run as headline numbers. Help can copy the versions for a support post. On phones the section
  dropdown and Save share one sticky row. Checked on WordPress 6.5, 7.1 and the development
  version: notices from WordPress and other plugins now sit above the cards instead of in the
  corner, the colour picker opens under its field, the browser tab names the open page, and
  Windows high contrast mode keeps the status marks. Sidebar links other than the open page are
  in the text colour, and the Help cards have icons.
- The comment thread has more breathing space and slightly smaller text (15px instead of 16px
  at the default root size, set by `--convocept-font-size`). More space between threads (2rem, `--convocept-gap`),
  between replies and before a comment's first reply (1.75rem), between the avatar and the text
  (1rem), above Upvote/Reply (0.75rem), and below the comment form (2.5rem).
- Settings screen fields use WordPress's own sizes, borders and fonts: every text, number,
  select and textarea field is 13px text in a 40px control, and all of them share one width
  (`--convocept-field-width`, 480px, narrower on small screens). Number fields are WordPress text
  fields of type number instead of our large stepper boxes. Only labels and help lines keep the
  Convocept style. A new e2e test fails when any settings page triggers a WordPress deprecation
  warning, so a coming component change shows up before it breaks the screen.
- The settings screen's sidebar shows the Convocept mark from convocept.com (speech bubble with
  two lines), in black with white lines instead of the site's green.
- Comments → Convocept keeps the open section in the address as `&tab=appearance` instead of
  `#appearance`: a reload or shared link opens the same section, sidebar links can open in a new
  tab, and Back and Forward move between sections.
- The Convocept screen has its own footer (version; maker linked to foxelabs.com; links to the
  Documentation, the Homepage (convocept.com) and a "Leave a ★★★★★ review" link to WordPress.org) in place of WordPress's admin footer, on that screen only.
- The settings screen moved from Settings to **Comments → Convocept**, next to the screens it
  works with.
- Comments → Convocept → Help links to the documentation (docs.foxelabs.com). Sidebar items
  sit 2px apart, so a hover highlight no longer touches the active item (design system version 6).
- M12 release preparation: the plugin is now named "Convocept – Fast Native Comment System"
  on WordPress.org; `readme.txt` rewritten for 1.0.0. WordPress.org icon, banner and screenshots
  are generated with `npm run wporg:assets` into `.wordpress-org/`.
- M11 privacy: Tools → Export/Erase Personal Data include comment subscriptions and the upvotes
  of registered users (counts are updated after erasing); Settings → Privacy has suggested
  policy text that follows the settings; new setting (Advanced → Privacy) to store new
  commenters' IP addresses in full, anonymized or not at all, also for imported comments.
- M11 uninstall: deleting the plugin always removes working state (locks, queued jobs, cached
  HTML, rate-limit counters, import files) on every site of a network. Settings, subscriptions,
  votes and comment meta are kept for a reinstall unless "Delete all Convocept data when the
  plugin is deleted" (Advanced → Uninstall) is on. Comments are never deleted.
- M11 i18n: counts the script updates ("5 comments", "Show 3 more replies") now come from the
  server with the language's real plural forms, and numbers are formatted in the site's
  language instead of the browser's. Notification emails are marked right-to-left for RTL
  languages. The translation template now includes the settings screen's strings.
- M11 accessibility: axe (WCAG 2.2 A/AA) runs in the end-to-end tests on the thread in every
  colour scheme on a classic and a block theme, while replying and previewing, in the moderator
  view, on the subscription page and on every settings page; keyboard focus and reduced motion
  are checked too.

- M9 settings screen (Comments → Convocept), built on the Foxe Plugin Admin UI: pages for
  General, Appearance, Composer, Moderation & spam, Subscriptions, Advanced, Import and Help;
  a save bar that appears only with unsaved changes; messages under invalid fields; works down
  to phone widths. Settings are saved through a validated REST route. Disqus imports now run on
  the Import page (Tools → Import leads there). Add-ons can add pages with
  `window.convoceptAdmin.registerPanel()` and describe their settings with
  `convocept_settings_schema`.
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

### Security
- Comments on private, draft or password-protected posts are no longer emailed to subscribers.
- Every subscription from an unproven address needs email confirmation, and the response no
  longer reveals whether an address is already subscribed. Pending subscriptions can be
  confirmed from the subscription page.
- Comment form posts that skip Convocept's form (straight to `wp-comments-post.php`) get the
  same spam checks; REST submissions need the form's signed token for the timing check.
- Guest upvotes count once per IP address, whatever the browser claims to be.
- Edits that add disallowed words are held; the edit token is sent in a header, not the URL;
  votes on comments of non-public posts are refused; the Disqus parser keeps libxml's limits.

### Fixed
- Settings screen: the unsaved-changes bar no longer adds empty space under the footer. It now
  sits in the page above the footer and sticks to the bottom of the window while you scroll.
- Themes can override Convocept's CSS custom properties with a plain `.convocept { … }` rule.
  The default tokens now sit in `:where()` blocks; before, the stylesheet (printed after the
  theme's) won on source order.
- "IP address stored with new comments" set to None or Anonymized no longer weakens WordPress's
  own checks. The address is now changed after the comment is saved, so the flood check,
  duplicate check and IP entries in the moderation and disallowed lists see the full address;
  before, with None, every guest looked like one visitor to the 15-second flood check.
- Replies to a pinned comment were not shown: core fetches each level of a threaded query
  with the same arguments, so the pinned query's `comment__in` filtered every reply out.
- Contrast: muted text in the "Match the theme" scheme (Twenty Twenty-One was 3.6:1, now 5.1:1),
  links and the subscribe link on dark and tinted backgrounds (accent mixed toward the text
  colour), and red moderator actions and error messages in the dark scheme.
- The upvote button's accessible name no longer shows a stale count after voting.
- The Trusted proxies field on the settings screen had no programmatic label.
- The Comment Subscription block did not load on WordPress 6.5 (it needed a script only
  WordPress 6.6+ provides).
- "Top" sort ordered wrongly when comments had other metadata, or reused cached "oldest" results.

### Removed
- The React prototype used to reserve the slug (never released): its files (`includes/`,
  `build/`, React sources in `src/`) and its data migration.
