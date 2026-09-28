# Performance

Convocept's goal is simple: **a post with comments should load as fast as the same post
without them.** This page lists the budgets we hold ourselves to, how they are checked, and
the measurements behind the claims on the plugin page.

## Budgets

These limits are checked on every change (`npm run size` in CI); a change that breaks one fails
the build.

| What | Budget | 1.0.0 |
|---|---|---|
| JavaScript on page load | 0 KB from files, 0 requests | 0 requests; a ~550 B gzipped inline loader |
| Interactive script, loaded when needed | ≤ 8 KB gzipped | 5.8 KB |
| Stylesheet, inlined next to the thread | ≤ 5 KB gzipped | 2.9 KB |
| Extra requests on page load | 0 | 0 |
| Layout shift caused by the comments | none | CLS 0 |

## How it stays fast

- **Server-rendered.** PHP renders the comment list into the page, so readers and search
  engines get every visible comment in the first response. No client-side rendering, no
  framework.
- **Script only when it's needed.** A tiny inline loader fetches the interactive script when the
  reader scrolls within 600 px of the comments, or focuses, taps or types in them. Clicks made
  before it arrives are replayed.
- **No extra requests.** The stylesheet is inlined just before the thread, so it neither blocks
  the article above nor causes a flash of unstyled comments. Icons are inline SVG; no fonts, no
  emoji images, no third-party calls.
- **Cached rendering.** Rendered comment lists are cached (object cache when available,
  otherwise transients) and refreshed automatically whenever a comment changes.
- **Cheap queries.** Core's comment query for the theme's comment template is skipped; Convocept
  loads one page of top-level comments and their replies.
- **Page-cache friendly.** Nothing personal is printed into the HTML, so the whole page can be
  cached. Personal state (logged-in name, saved details, your votes) is one small request made
  only when the reader interacts.
- **Optional live updates** are off by default. When on, they check only while the tab is
  visible, and an unchanged thread costs one empty `304` response.

## Measurements

All numbers are from a local `wp-env` site (Docker on macOS, PHP 8.3, `WP_DEBUG` on, no
persistent object cache), so a production server should do better.

**Lighthouse, mobile, Twenty Twenty-Five.** A post with 200 comments compared with the same
post with comments closed:

| | 200 comments | No comments |
|---|---|---|
| Performance score | 99–100 | 100 |
| Cumulative Layout Shift | 0 | 0 |
| Total Blocking Time | 0 ms | 0 ms |
| Requests | 10 | 10 |
| Transferred | +8 KB (first 20 comments and the inline CSS) | — |

**Server time.** A post with 2,000 comments: the first page of comments renders in about 57 ms
with 10 database queries. Full page time to first byte: 0.20 s with Convocept, 1.25 s with the
theme's default comment template.

**Disqus import.** An export with 100,000 posts (98,000 comments after skipping spam): dry run
4.7 s, import 126 s through WP-CLI, re-running the same export 11 s with no duplicates.

## Measure it yourself

1. Pick a post with many comments and a copy of it with comments closed.
2. Run Lighthouse (Chrome DevTools → Lighthouse → Mobile → Performance) on both, three times
   each, and compare the medians.
3. In DevTools → Network, reload the post: no Convocept request appears until you scroll to the
   comments.

The source of the budgets is `package.json` (`size-limit`); the loading behaviour is covered by
the end-to-end tests in `tests/e2e/specs/performance.spec.js`.
