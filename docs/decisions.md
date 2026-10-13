# Decisions

Decisions not fully covered by `PLAN.md`. Newest first.

## 2026-10-13 — WordPress.org icon and banner
- Both use the convocept.com palette (emerald, amber, purple, blue on black, with black text on
  the hues) and its fonts, Bricolage Grotesque and Figtree, loaded from Google Fonts when
  `npm run wporg:assets` runs. They replace the placeholder mark.
- **Banner:** dark, in the style of the WooCommerce listing (UI cards on a solid ground, no
  logo or name, since the directory prints both below it). Three columns of equal height with
  24px gutters: feature list with hued icon tiles, a two-comment thread, "0 KB" stat and the
  composer toolbar. Every feature named ships in 1.0.
- **Icon:** three bubbles (amber comment, purple reply, emerald typing) from the launch post
  image, on a black tile with rounded corners (transparent in the PNGs). The admin sidebar keeps
  the single-bubble mark.
- **Animated icon:** the directory always picks `icon.svg` first, then PNG, JPG and GIF
  (`get_plugin_icon()` and `find_best_asset()` in the plugin-directory source), so an animated
  GIF would never show beside them. `icon.svg` carries a CSS animation instead: the bubbles pop
  in once, then the dots keep typing; `prefers-reduced-motion` shows it still. It stays sharp,
  keeps antialiased transparent corners (GIF transparency is 1-bit) and is under 2 KB. The
  static PNGs remain the required fallback.

## 2026-10-09 — Final review before 1.0.0
- **Notification queue:** one non-autoloaded option per job (`convocept_notification_job_{id}`,
  value = cursor), listed with a direct query. One shared array lost or repeated jobs when a
  request queued a comment while the sender ran (each side wrote back a stale copy).
- **Fragment cache without an object cache:** one transient per slot (post, page, sort,
  expanded comment) storing `{version, html}`. Core bumps "comments last changed" on every
  comment meta write, so a vote anywhere used to add a new row per viewed post.
- **`convocept_expand`** only counts when it names a top-level comment on the page (it is part
  of cache keys).
- **Edits:** an edit that adds a link is held, because Akismet and similar filters only see new
  comments. The REST timing check caps the reported time at the form token's age.
- **Re-subscribing** resets `created_at`, which the 48-hour confirmation expiry is measured from.
- Deferred to 1.0.1: a Cloudflare trusted-proxy preset or notice (behind a proxy without
  trusted proxies, all visitors share one rate-limit bucket); one confirmation email covering
  several pending subscriptions (today the second needs Confirm on the subscription page).

## 2026-10-09 — Subscription page: the thread's design
- The page from subscription emails was bare markup inside the theme, so themes such as Raft
  gave it a huge heading and large theme buttons. It is now a Convocept card like the thread.
- Its wrapper is `<section id="comments" class="convocept convocept-subscriptions">`, so every
  `#comments.convocept` rule (reset, tokens, buttons, schemes) applies unchanged. A second ID
  would mean doubling 130 selectors; the page has no other comments section, so the ID is free.
- It prints the same inline stylesheet as the thread (`FrontAssets::stylesheet()`); its own
  rules add about 0.25 KB gzipped to the 5 KB budget. No JavaScript.

## 2026-10-01 — Thread: own design
- Matching each theme failed in practice (screenshots of 12 themes): uppercase or dark theme
  buttons, hidden checkboxes, full-width or grey-band threads, small text. Like ReplyBox, the
  thread now draws its own simple design (Design canvas "Convocept thread design",
  https://claude.ai/artifact/EvgK9Yq6bfWEppMrkasjnv) and takes only the font family.
- Isolation without Shadow DOM or an iframe (the thread must stay server-rendered HTML for SEO,
  no-JS and page caches): `#comments.convocept :where(:not(svg, svg *)) { all: revert }` drops
  theme styles inside the thread, then every rule is `#comments.convocept …`, which outranks
  class- and element-level theme rules and ties `#respond` / `#commentform` ones. SVGs are
  excluded because `revert` drops presentation attributes. Tokens stay in `:where()` so themes
  can still override them. Sizes in px (themes change the root font size).
- Colour schemes: `light` (default), `dark`, `system`. `auto` (inherit the theme's colours)
  was removed; unreleased, so saved `auto` values simply fall back to the default.
- Width: `min(720px, --wp--style--global--content-size, 100vw - 32px)`, centred.
- The Follow link in the header opens the in-thread subscribe form via `:target` without
  JavaScript and toggles it with (`.is-open` / `.is-closed`); a new
  `convocept_thread_header_args` filter lets SubscribeForm set `follow`.
- Moderator actions moved into a disclosure menu (not an ARIA menu: plain buttons, Escape and
  outside click close it).
- `comments-area` and per-theme width classes are no longer added to the wrapper: they pulled
  theme styles (backgrounds, padding) back onto the thread.
- Screenshot matrix: `npm run screenshots:themes` (bin/theme-screenshots.mjs) shoots the thread
  in 12 themes on the dev site, desktop and phone, into `artifacts/themes/`. Run it after
  changing the thread's CSS or markup.

## 2026-10-01 — Settings → Discussion: explain, don't remove
- Checked every Discussion option. Followed as before: name/email required, registration,
  closing old posts, the cookies checkbox, manual approval, previously approved author, link
  limit, moderation and disallowed lists (new comments go through
  `wp_handle_comment_submission()`, edits are checked again), both notification emails (links
  filtered to our pages), avatars (part of the fragment cache key) and the per-post defaults.
- Replaced on posts Convocept handles: threaded comments and depth (`max_depth`), paging, comments
  per page and first page shown (`per_page`, Load more; `/comment-page-N/` works with core paging
  off), comment order (`default_sort`). Pingbacks and trackbacks are stored but not shown.
- Core options are not hidden or disabled: they still apply to other post types and when
  Convocept is off, and hiding core settings is close to admin hijacking for WordPress.org.
  Instead an info notice on that screen only (`Admin\DiscussionNotice`), shown while Convocept
  is on for at least one registered post type, links to the General page.
- Convocept's own behaviour is set only in Convocept's settings. It does not read
  `thread_comments` (turning threading off in WordPress does not flatten Convocept's thread) or
  the other replaced options.

## 2026-10-01 — Admin screen review: robustness across WordPress versions
- Checked on WordPress 6.5 (the minimum), 7.1 and trunk (7.2-alpha) with the settings, import
  and accessibility e2e specs, plus a new "layout holds" spec (no sideways scroll, Save in view
  while scrolling, the unit inside its field, card borders, active item in the admin theme
  colour, the checked tile's dot, phone width). Run it on other versions with
  `.wp-env.override.json` (`{ "core": "WordPress/WordPress#6.5" }` or `#master`); the thread
  specs need their themes, which those cores don't install.
- Rules now in the Admin Kit README: leave core controls alone apart from label/help text;
  draw a control fully (`appearance: none`) when our look differs, never restyle half of one
  (the tile radios were relying on forms.css `::before`); read `--wp-admin-theme-color` and
  `--wp-admin--admin-bar--height`; no screen background of our own; stable props only.
- `accessibleWhenDisabled` is not in WordPress 6.5, so Save and Upload use `aria-disabled` and
  ignore clicks while they can't act: focusable on every version, no unknown-prop warning.
- The colour picker opens in a panel under its row instead of a `Dropdown` popover, which on
  6.5 flipped above the row and ran under the admin bar.
- Notices printed by WordPress or other plugins (`.notice`, `.update-nag`) are moved into the
  content column on mount, as core's common.js does with `.wp-header-end`; they used to hang in
  the top-left corner above the screen.
- Accessibility: sidebar group captions are no longer `h2` (they came before the page's h1); the
  save status is an always-present live region; nav links read "General Needs fixing" with a
  space; the section dropdown shows flags and counts; the browser tab title names the page;
  `scroll-padding-top` keeps focused fields out from under the sticky header; the hidden file
  input is out of the tab order; units are part of the number field's description; Windows high
  contrast mode keeps dots and progress and outlines the active page and selected tile.

## 2026-09-30 — Admin screen on the new Foxe Plugin Admin Kit
- After a review and a mockup (claude.ai canvas "Convocept admin — core WP look"), the owner chose
  a look closer to core WordPress and a new design system, **Foxe Plugin Admin Kit**
  (https://claude.ai/artifact/3GMTBRMn2xZB5jk7wZfX9w). The old "Foxe Plugin Admin UI" stays as it
  was, for reference; nothing here follows it any more.
- The accent is the user's admin theme colour. `ui.css` reads `--wp-admin-theme-color` and never
  sets it (the old black override ignored every user's colour scheme). Ground `#f0f0f1`, cards
  with a 1px `#e0e0e0` border and 8px radius, radii only 2 / 8 / pill.
- Save moved from the floating ink bar into the sticky page header, disabled at rest, with
  "N unsaved changes" / "N settings need fixing" and Discard beside it; it reads "Saved" after a
  save. The floating bar overflowed phones and sat off-centre from the cards.
- The sidebar stays (owner's call) but is sticky, uses a light theme-colour tint for the active
  item, shows On/Off under the name (the header chip is gone) and a count badge for comments
  waiting for review. Below 782px the section dropdown sits in the header next to Save.
- Pages merged: Composer and Subscriptions are one "Comment form" page (`tab=comment-form`);
  "Moderation & spam" is "Spam & moderation", with review first and the fixed checks as a
  read-only list with ticks. No redirects from the old tabs: the plugin is unreleased.
- Content is one left-aligned 720px column, 32px from the sidebar and the header. Number fields
  are 180px with the unit inside (a native input in BaseControl: stable components have no
  suffix slot). Notices come in four tones (success, error, warning, info); errors and warnings
  cannot be dismissed. Import shows Upload › Review › Import › Done steps and stat tiles.
- Appearance gains a static preview of one comment in the chosen accent and scheme (admin only,
  not the real thread markup) and the colour picker offers the theme's `theme.json` palette
  (six-digit hex only, as the setting stores) and suggested colours.
- Uses `color-mix()` for the theme tints, since the theme colour is only known at runtime.

## 2026-09-28 — Settings fields keep WordPress's own look
- Compared with a mockup built only from `@wordpress/components` (removed after review). The
  owner kept our layout (sidebar, cards, floating bar, tiles) but preferred core inputs.
- `ui.css` no longer restyles inputs, selects or textareas (font size, border, radius). It
  styles only labels and help lines, plus a red border on `[aria-invalid="true"]` inside an
  invalid field: our attribute, not a core class. The core class names `ui.css` depends on are
  the label/help/toggle-label ones, buttons and snackbars; keep that list short.
- `NumberField` wraps `TextControl type="number"` (stable) rather than `NumberControl`
  (still `__experimental`) or our own steppers.
- Guard against WordPress updates: stable components only, `__next40pxDefaultSize` and
  `__nextHasNoMarginBottom` on every control (the coming defaults), and an e2e test that fails
  on any deprecation warning on the settings pages (wp-env runs with `SCRIPT_DEBUG`).

## 2026-09-28 — Token overrides and the stored-IP setting
- Default CSS tokens live in `:where(.convocept…)` blocks (zero specificity). The stylesheet is
  inlined in the body, after theme styles, so equal-specificity defaults used to beat a theme's
  `.convocept { --convocept-radius: … }`. Layout properties stay on `.convocept`. The accent
  stays an inline style on the wrapper: it is a setting, and the docs point to it.
- `commenter_ip` is applied on `wp_insert_comment` (priority 0) with a one-column update, not on
  `pre_comment_user_ip`. Core runs `wp_filter_comment()` (which applies that filter) before
  `wp_allow_comment()`, so the flood/duplicate checks and moderation/disallowed IP entries saw
  '' or an anonymized address. Akismet was unaffected (it runs on `preprocess_comment`).

## 2026-09-28 — Settings sections in a `tab` query argument
- Sections are addressed as `edit-comments.php?page=convocept&tab=import`, the usual WordPress
  admin pattern, instead of `#import`. The server can read it (`SettingsPage::url()` builds it for
  redirects such as Tools → Import), and it survives redirects that drop a hash.
- Switching sections does not reload: a plain click calls `history.pushState` and `popstate`
  handles Back/Forward, so the unsaved draft is kept. Sidebar links carry the full URL so
  middle-click and Cmd-click open a new tab. Unknown or missing `tab` shows General.
- Design system version 10 records this in the Sidebar README.

## 2026-09-28 — Own footer on the Convocept screen
- **PageFooter** added to the design system first (version 8–9), then built: plugin, version and
  maker on the start side; Documentation, convocept.com and WordPress.org on the end side. On the
  Convocept screen only, `admin_footer_text` and `update_footer` return '' (hooked on the page's
  `load-` action) and the app stylesheet, which loads nowhere else, hides `#wpfooter`.
- Footer links are labelled by what they lead to, never by bare domain: Documentation, Homepage
  (convocept.com), and "Leave a ★★★★★ review" to the WordPress.org reviews form. "Foxe Labs" links
  to foxelabs.com. The review link is the plugin's only review ask (no notices or nags), which
  WordPress.org guidelines allow. Stars are aria-hidden; screen readers hear "five-star".
  Design system version 13.
- Content keeps `space-8` below the last card instead of 120px; the footer gets 96px of bottom
  padding only while the FloatingBar shows, so short pages end with the footer at the window's
  bottom and the bar never covers its links.

## 2026-09-28 — Settings screen under Comments
- **Comments → Convocept instead of Settings → Convocept** (owner's call, before 1.0): the app
  holds import today and moderation (M14) and Insights (M13) later, so it belongs with comments.
  `add_comments_page()` with `manage_options`, so editors, who see the Comments menu, don't see
  it. No redirect from the old address: nothing is released, so there is nothing to stay
  compatible with. The Plugins-screen "Settings" link and Tools → Import follow `SettingsPage::url()`.
- **No backward-compatibility code before 1.0** (owner's rule): migrations were renumbered to
  start at 1 (the prototype's migration 1 was deleted earlier). Development sites already at
  `convocept_db_version` 3 simply have nothing left to run.

## 2026-09-28 — M12 release preparation
- **Name "Convocept – Fast Native Comment System"** in both the `Plugin Name` header and the
  readme title (WordPress.org shows the header; the readme title should match). "Convocept"
  stays first, and no third-party trademark is in the name: Disqus is in tags and text only.
  Menus, the settings screen and emails keep the short name "Convocept". The slug stays
  `convocept`.
- **Side effect in tests:** outside WordPress.org, WordPress and the e2e utilities derive a
  plugin's slug from its name, so `activatePlugin()` now takes
  `convocept-fast-native-comment-system` and the Plugins-screen row is found by
  `data-plugin="convocept/convocept.php"`.
- **readme.txt** rewritten from the prototype's text: every claim matches what 1.0 does (for
  example guests *can* edit within the edit window; wpDiscuz/StCR importers are "1.1").
  Tags: comments, disqus, subscribe to comments, comment form, spam (only the first five count;
  "comment system" is already in the name, so its tag slot went to "comment form").
- **readme.txt tuned for search** (owner's request): WordPress.org ranks the name, tags and short
  description highest, then the description. The short description (≤ 150 characters) and the
  first paragraph carry the main terms (comment system, threaded replies, upvotes, comment
  subscriptions, spam protection, Disqus importer); "Disqus alternative", "wpDiscuz" and
  "Subscribe to Comments Reloaded" appear in text and FAQ questions people search for, never in
  the name or as keyword lists (the directory penalises stuffing). Section headings use one emoji
  each, which the directory renders; every claim matches what 1.0 does.
- **WordPress.org assets are generated**, not hand-made: `npm run wporg:assets` (its own
  Playwright config, `tests/e2e/wporg/`) seeds a demo discussion on the tests site and writes the
  icon, banner and the six readme screenshots to `.wordpress-org/`, which is committed and not
  in the release zip (it goes to the SVN `assets/` folder). Re-run it at release so the admin
  shows `1.0.0`, not `1.0.0-dev`. Icon and banner use the placeholder mark until a real logo exists.
- The generator found that **replies under a pinned comment were never shown** (fixed, with a test).
- **Plugin Check** (run on the release zip, unpacked on the dev site): only the stable tag
  mismatch remains, which the version bump to 1.0.0 fixes. Its 11 `UnescapedDBParameter`
  warnings were table names built from `$wpdb->prefix`; queries now pass them with the `%i`
  identifier placeholder (WordPress 6.2+), so they are provably escaped. Running it on the
  working copy also lists dev files (dotfiles, `*.dist`, CLAUDE.md, PLAN.md); none are in the zip.
- **Security review before release** (read-only review of every route, template, token and the
  importer; no critical or high issues). Fixed:
  - Site-wide and per-post subscribers were emailed comments on private, draft and
    password-protected posts. The notifier now skips posts that are not publicly viewable,
    both when queueing and when sending.
  - Double opt-in could be skipped for any address that already had one active subscription,
    so anyone could subscribe someone else to the whole site; responses also showed whether an
    address was subscribed. Every unproven address now confirms by email and always gets
    "pending". To keep that painless, the subscription page (reachable only from emailed links)
    has a Confirm button for pending subscriptions.
  - Plain form posts to `wp-comments-post.php` without Convocept's marker skipped its checks. On
    posts Convocept handles they now get them (no token means the timing check holds the comment).
    The REST composer sends the form's signed token; without it the browser's elapsed time is
    ignored and the comment is held.
  - Guest votes were keyed on IP + User-Agent, so changing the User-Agent voted again. Now the IP
    alone (people behind one shared IP share one vote; accepted for a low-stakes upvote).
  - Edits that add words from the disallowed list are held; the edit token goes in a header
    (`X-Convocept-Edit-Token`) instead of the URL; votes on comments of non-public posts are
    refused; the importer no longer passes `LIBXML_PARSEHUGE`.
- **PHP 7.4 / WordPress 6.5 locally**: a wp-env override to 7.4 + 6.5 hung twice pulling images,
  so that combination is covered by PHPCompatibility (`testVersion 7.4-`) and the CI matrix job.
- **PHPUnit empties Convocept's tables at start**: the e2e tests share the tests-site database
  and WordPress's test installer only resets core tables, so a subscriber left by a browser test
  made a notification test flaky.

## 2026-09-28 — M11 accessibility, i18n, privacy, uninstall
- **Privacy exporters and erasers** cover subscriptions (by email hash) and the upvotes of a
  registered user with that address. Guest votes are a one-way hash of IP and browser and cannot
  be found by email, which the policy text says. Comments are core data: core's own comment
  exporter/eraser handle them.
- **`commenter_ip` setting** (keep / anonymize / none, default keep as WordPress does) filters
  `pre_comment_user_ip`, so it applies to every path through `wp_new_comment()`; the Disqus
  importer applies it too (`wp_insert_comment()` skips that filter). Spam checks (Akismet) run
  earlier and still see the full address. Existing comments are not rewritten. Akismet keeps its
  own copy of the IP in comment meta; that is Akismet's data.
- **Uninstall keeps data by default** (`delete_data` off), as PLAN.md asks. Before M11 it always
  removed settings and meta; now only working state goes unless the owner opts in. The logic
  moved to `Convocept\Uninstaller` so it can be tested; `uninstall.php` loads the autoloader
  itself because WordPress does not load the plugin on uninstall. Deleting also removes
  `_convocept_disqus_id`, so a later re-import only recognises comments the Disqus plugin synced.
- **Plural counts come from PHP.** The front end used `countOne`/`countMany` strings, which is
  wrong for languages with more than two plural forms. REST responses now carry `count_text`
  and `more_text` built by `Render\Labels` (also used by the templates). The composer's counter
  became "Characters left: %s", which needs no plural.
- **Script translations**: `make-pot` scans `assets/dist` (the built files WordPress loads)
  instead of `assets/src`, because JSON translation files are matched by the md5 of the path in
  the reference. The old command also excluded `assets/dist` by accident (`dist` matched it), so
  the settings screen's strings were missing from the .pot.
- **Front-end CSS was already direction-neutral** (logical properties only); no RTL stylesheet
  is needed. The upvote button's name no longer includes the count in `aria-label` (it went
  stale after voting): it is "Upvote" plus the visible count.
- **axe fails only on serious/critical issues inside Convocept's markup** (`section.convocept`,
  `.convocept-subscriptions`, `.convocept-admin`); the theme around the thread is not ours to
  fix. It waits for running CSS transitions first, or it samples colours mid-transition.
- **New colour tokens**: `--convocept-link` (accent mixed 65% with the text colour) for accent
  used as text, and `--convocept-danger` (red mixed 70% with the text colour). Mixing toward the
  text colour darkens them on light backgrounds and lightens them on dark ones, so one rule
  keeps 4.5:1 in auto, light and dark schemes. Buttons keep the plain accent as background.
  `--convocept-muted` in the auto scheme went from 62% to 75% of the text colour.
- **TextareaControl ignores an `id` prop for its label** (it labels its own generated id), so
  TextareaListField no longer passes one. Worth fixing in the design system's TextareaField
  notes when the shared package is extracted.

## 2026-09-28 — Own Comments screen in 1.1, beside the core one
- The owner asked whether Convocept should replace the core Comments menu with its own list in
  the admin app (DataViews). Decided: **yes to our own screen, no to replacing core**, and not in
  1.0 (M11 and M12 come first). Planned as M14 for 1.1.
- **Why not replace**: other plugins (Akismet, anti-spam, WooCommerce reviews) extend the core
  list table with columns, row actions, bulk actions and filters that DataViews cannot show, so
  they would break silently; core links (admin bar, Activity widget, moderation emails, post
  editor) all point to `edit-comments.php`; taking over a core menu by default is close to the
  WordPress.org admin-hijacking rule. An opt-in "Use Convocept for the Comments menu" setting may
  come later, with the core screen still reachable.
- **Data** comes from core `/wp/v2/comments` plus our fields, not a new comments API.
- **DataViews** is bundled in our admin build (core has no script handle for it) and its API still
  changes between WordPress releases; acceptable for admin-only code, pinned and tested on 6.5.
- **Menu**: when the app holds moderation it moves to a Comments → Convocept submenu, next to
  Comments → Insights (M13), with a redirect from Settings → Convocept.

## 2026-09-28 — M9 settings screen
- **Built on the Foxe Plugin Admin UI design system** (claude.ai artifact "Foxe Plugin Admin UI").
  The owner chose to build the components inside Convocept first (`assets/src/admin/ui/`, no
  Convocept logic in it) and move them to a shared package later. Tokens are `--convocept-*`
  custom properties and classes use `convocept-`, scoped under `.convocept-admin`;
  `--wp-admin-theme-color` is set to ink so WordPress controls match.
- **Components added to the design system first** (version 3): TextField, SelectField,
  TextareaField, CheckboxList, ColorField, FileField, ProgressBar, SummaryList, Snackbar. Control
  edges moved to `stroke-control` (3:1): choice tiles and the table search field used
  `stroke-tile` (1.3:1).
- **Pages instead of tabs**: Settings (General, Appearance, Composer, Moderation & spam,
  Subscriptions, Advanced), Manage (Import), More (Help). Routing is the URL hash. Below 782px
  the sidebar is a section dropdown. No add-ons exist, so there are no upsells anywhere.
- **Saving**: one draft across pages; the save bar appears only while it differs from what is
  saved, counts the changes, and blocks saving while a value is invalid. Messages come from
  `Settings::validate()` on the server and the same rules in `app/validate.js` for instant
  feedback. The route saves only the changed keys and nothing at all when one is invalid.
  `convocept_settings_saved` fires afterwards; rendered comment lists are keyed by the settings,
  full-page caches refresh on their own schedule.
- **Settings → Convocept**, not a top-level menu, with a Settings link on the Plugins screen.
  The bundle (37 KB JS, 13 KB CSS, WordPress packages external) loads on that screen only.
- **Import moved into the app** (Manage → Import) on new REST routes, and the old server-rendered
  Tools → Import screen was removed. Tools → Import still lists "Disqus (Convocept)" and redirects
  to the Import page. The screen needs JavaScript now; the no-JS fallback is WP-CLI.
- **Moderation & spam and Composer show facts, not new settings**: the spam checks in place, the
  review queue, and WordPress's own Discussion rules with a link. New settings wait for real needs.
- **NumberField is our own stepper** around a native number input: the design system maps it
  to `__experimentalNumberControl`, which the WordPress lint rules forbid.
- **Fixed a WordPress 6.5 break**: `@wordpress/scripts` compiles JSX for React's automatic runtime,
  whose `react-jsx-runtime` script handle only exists from WordPress 6.6. The subscribe block
  (M6) already depended on it, so it could not load on 6.5. A `babel.config.js` compiles JSX to
  `createElement` from `@wordpress/element` for both the block and the app.
- **Light sidebar** (design system version 4): the dark plugin sidebar sat right beside
  WordPress's own dark admin menu, which read as one 408px slab and two competing menus. The
  sidebar is now `surface` with a `stroke-header` edge and an `ink-raised` active item; ink is
  kept for the save bar and the feature card. Changed in the design system first, for every
  Foxe plugin.
- **Logo**: a placeholder mark (white speech bubble on an ink tile) until Convocept has one.

## 2026-09-26 — Prototype removed
- Deleted `includes/`, `build/` and the React prototype in `src/` (App.jsx, components, hooks, …).
  Nothing loaded them, but `bin/build-zip.sh` ships all of `src/`, so they would have been in the
  release zip.
- Deleted migration 1 (`PrototypeData`): it only moved data of a build that was never released,
  and it dropped `live_updates` / `poll_interval`, names M4 later reused for real settings.
  Migrations continue at 2 so development installs already at version 3 stay consistent.
- `readme.txt` still describes the prototype; it is rewritten in M12.

## 2026-09-26 — M8 Disqus importer
- **Format checked against real sources**, not guessed: Disqus's own WordPress plugin (thread
  identifier `"{post ID} {guid}"`, synced comments keep `dsq_post_id` meta) and the isso
  importer (`<thread dsq:id>`, `<post>` with `<message>` HTML, `<createdAt>` UTC, `<isDeleted>`,
  `<isSpam>`, `<author>` name/email/username, `<ipAddress>`, `<thread dsq:id>`, `<parent dsq:id>`).
- **Two phases.** The dry run streams the XML once with XMLReader (one element in memory at a
  time; `.gz` read directly) and writes an NDJSON work file plus thread and skipped-parent maps.
  Batches read the work file from a byte offset, so resuming never re-parses the export and
  each batch starts instantly. The dry run buffers its writes (~1 MB): one write per comment took
  118 s instead of 4.7 s for 100k comments on Docker's file sharing.
- **Threads → posts:** identifier (post ID, accepted only when the guid matches), then the thread
  URL, then the same path on this site's domain (moved domain / HTTPS). Unmatched threads are
  reported and skipped; `convocept_disqus_thread_post_id` maps the rest.
- **Idempotent:** `_convocept_disqus_id` on every imported comment; comments with the Disqus
  plugin's `dsq_post_id` meta or Disqus `<id>wp_id=N</id>` (originally from this site) count as
  "already on this site". Lookups are one query per batch.
- **Parents:** spam/deleted comments are skipped and their replies move to the nearest kept
  ancestor. A parent that appears later in the file is attached when the import finishes. A
  parent on a different post makes the reply top-level (found in the 100k benchmark: treating it
  as "comes later" plus per-row unindexed meta queries stalled the finishing step).
- **Content:** Disqus HTML → paragraphs/line breaks become newlines and only core's comment
  tags are kept (`wp_kses` `data` context). `comment_agent` = `Disqus import`. Imported
  comments are guests (`user_id` 0) even when the email matches a user: Disqus emails aren't
  verified for guests, so linking would let anyone claim a site account's badge.
  `convocept_disqus_comment_data` can link them.
- **No side effects:** imports never notify subscribers (`convocept_should_notify` is false
  during batches) and comment counts are updated once per post per batch (deferred counting).
- **Privacy:** the upload and work files (emails, IPs) live in
  `uploads/convocept-import/{32 random chars}/` with `index.php` and a deny-all `.htaccess`, and are
  deleted when the import finishes, is cancelled or the plugin is uninstalled.
- **Admin UI** is the core Tools → Import screen (no settings screen needed): upload → dry-run
  report with a sample → Start → progress → report. Plain forms (one batch per click) work
  without JavaScript; a script under 1 KB runs the batches through admin-ajax. Needs `import` and
  `moderate_comments`. Batches stop after 500 items or 15 s. WP-CLI: `wp convocept import`.
- **Only one import at a time** (one job option; an `add_option` lock stops parallel batches).
- **Front end never loads importer code:** wired only under `is_admin()` and WP-CLI; an e2e test
  lists the classes loaded while rendering a post.
- `_convocept_disqus_id` survives uninstall so a later re-import can't duplicate comments.
- **Benchmark** (wp-env on macOS Docker, WP-CLI, 100k-post export as .xml.gz, 100 threads, 2% spam,
  40% replies): dry run 4.7 s; import of 98,000 comments 126 s (~780/s, 1,000 per batch); re-running
  the same export 11 s with all 98,000 recognised and no new rows. In wp-admin a batch is 500
  comments or 15 s, so no request comes near a 30 s limit; the dry run is the only single pass
  (seconds even for 100k), and larger exports are pointed to WP-CLI.

## 2026-09-26 — M7 structured data
- **`Comment` under the article, not `DiscussionForumPosting`.** Google's discussion forum docs say
  not to use that markup for "an article or blog written directly by an agent for the website
  (even with comments)", and recommend nesting comments under the item they belong to. Comments
  are `Comment` items in the article's `comment` property; replies nest under their parent
  (the real parent, even when the page shows them flattened past `max_depth`). Each carries
  `author` (`Person`), `datePublished`, `text` (plain text as rendered), `url`/`@id`
  (`#comment-N` on the page that shows it), `dateModified` when edited and `upvoteCount` when
  votes are on.
- **Only what the page shows:** the current comment page and sort, and replies visible before
  "Show more replies" (the renderer and the schema share `ThreadRenderer::visible_ids()`).
  Pending comments are never included. The article's `url` stays the first page, as Google asks
  for multi-page threads.
- **Yoast SEO / Rank Math:** comments are appended to the article node in their graph
  (`wpseo_schema_graph`, `rank_math/json_ld` at 999), or to the `WebPage` node when there is no
  article, so there is one entity per page. Otherwise a standalone node (`BlogPosting` for posts,
  `WebPage` for pages, `Article` for other types) is printed in `wp_footer`, only when the page
  actually rendered the thread. Yoast's graph is built in `wp_head`, before a classic theme
  renders comments, so that path can't check "rendered"; it relies on the post being enabled.
- **Guest websites are left out** of `author.url`: a JSON-LD URL can't carry
  `rel="nofollow ugc"`, so it would hand spammers a followed link. Registered users' URLs stay.
- Items are cached like the rendered list (keyed by `last_changed`), because turning comments into
  text runs the Markdown parser / `comment_text` again. Output uses `JSON_HEX_TAG`, so comment
  text can't close the script element. New setting `structured_data` (default on).
- **Validation:** schema.org validator reports 0 errors and 0 warnings on the e2e fixture.
  Google's Rich Results Test has no local/API access and shows no rich result for comments on an
  article anyway (only its Article checks); recheck against the public site before release (M12).
- **Crawlable pages** were already in place from M1/M3: plain `<a href>` links to core comment
  page URLs (`cpage` / `comment-page-N`), `rel="prev/next"`, and `nofollow` on sort, reply and
  "show more" links. An e2e test now covers it without JavaScript.

## 2026-09-26 — M6 subscriptions
- **Signed link tokens instead of stored token hashes.** PLAN §7 asked for random tokens stored only
  as hashes, but notification emails must carry a working unsubscribe link long after the
  subscription was created, and a hash can't be turned back into a link. Tokens are
  `{id}.{HMAC(salt, purpose|id|token_key)}`: unguessable, purpose-bound (confirm vs manage),
  revocable per row by rotating `token_key`, never containing the email address, and useless
  with only a leaked database (the salt lives in wp-config.php). Confirm links expire after 48 h.
- **No state change on GET.** Mail scanners open links, so confirming and unsubscribing are
  buttons (POST). The exception is RFC 8058 one-click (`List-Unsubscribe=One-Click` POST).
- **Double opt-in** (default on) is skipped for a logged-in reader's own account email and for
  addresses that already have an active subscription. Confirmation emails: at most one per
  address per 10 minutes. Subscribe responses are identical whether or not an address exists.
- **Queue:** approving a comment records a job (comment ID + cursor) in an option; WP-Cron (or
  Action Scheduler when installed) sends `batch_size` (default 50) per run and reschedules. One
  email per address per comment (GROUP BY email hash), never to the comment's own author.
  Queued from `wp_insert_comment` / `transition_comment_status`, so every source is covered;
  importers skip with `convocept_should_notify`.
- **Subscription page** is a query-var URL (`/?convocept=manage&convocept_token=…`) rendered in
  the theme's own header and footer (classic `get_header()`, block `block_template_part()`), with
  `noindex, noarchive`, a strict referrer policy, `nocache_headers()` and `DONOTCACHEPAGE`.
- **Known limitation:** with PHP's `mail()` transport, PHPMailer RFC 2047-encodes headers longer
  than ~60 characters, which mangles `List-Unsubscribe`. SMTP / API senders (what high-volume
  sites use, and what Gmail/Yahoo bulk rules expect) send it raw; the link in the email body always
  works.
- **Two bugs fixed on the way:** "Top" sort now joins the score meta with the key in the `ON`
  clause (other comment meta broke the order), and uses its own `cache_domain` (core's query
  cache keys ignore custom query vars, so it reused "oldest" results).

## 2026-09-26 — M5 votes, pins and moderation
- **Upvotes** use the `{prefix}convocept_reactions` table (migration 2, planned in §5.4) as the
  reaction `up`; `INSERT IGNORE` + a unique key make one vote per voter a database guarantee.
  Voter IDs are HMAC hashes of the user ID, or of IP + user agent for guests (no raw IPs stored).
  Counts are cached in `convocept_reactions` meta and mirrored to `convocept_score` for "Top".
  The session endpoint returns the visitor's votes on the post, so the page HTML stays shared.
- **Pinned** comments (`convocept_pinned` meta, top-level and approved only) lead page 1 in every
  sort, carry a "Pinned" badge, and are excluded from pagination counts and deep-link positions.
- **Front-end moderation** (session `can_moderate`): approve / spam / trash / pin / unpin in
  place and an "Edit in dashboard" link; pending comments come from an authenticated
  `/posts/{id}/pending` call and never reach cached HTML. Routes need `moderate_comments` and
  `edit_comment` (or `edit_post` for the pending list) plus the REST nonce.
- **Moderators skip Convocept's checks** (timing, honeypot, links), matching core, which never
  holds a moderator's comment.
- **Early clicks and typing before the lazy script loads:** the loader queues a click on a
  script-only button (vote, editor tab, formatting) and replays it once ready; links are never
  intercepted. It also records when the reader first focused the thread, so someone who starts
  typing before the script arrives isn't held by the timing check.
- **Test infrastructure:** the e2e helper mu-plugin is mapped as a directory. Single-file bind
  mounts go stale (0 bytes in the container) when an editor replaces the file.
- The reactions table survives uninstall until M11 adds the "delete all data" option.

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
  *(Removed 2026-10-01: the thread styles itself; see "Thread: own design".)*
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
