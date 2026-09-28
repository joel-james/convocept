=== Convocept – Fast Native Comment System ===
Contributors: joelcj91, foxelabs
Tags: comments, comment system, disqus, subscribe to comments, spam
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast, modern WordPress comments with no JavaScript on page load. Replies, upvotes, email subscriptions and a Disqus importer. Your data stays yours.

== Description ==

Convocept replaces your theme's comment section with a fast, modern comment system that runs on **native WordPress comments**. Every comment stays in your own database, in the standard WordPress tables, so Akismet, anti-spam plugins, exports, backups and the Comments screen keep working. Nothing is sent to an outside service.

It is built for speed first. The thread is rendered on the server, straight into the page, so readers and search engines see every comment immediately. **No JavaScript loads with the page.** The small interactive script (under 6 KB gzipped) loads only when a reader scrolls near the comments or starts typing. Posts with hundreds of comments score the same in Lighthouse as posts with none.

= Why Convocept =

* **Fast:** 0 KB JavaScript and 0 extra requests on page load; styles under 3 KB, inlined; no layout shift. The budgets are checked on every change.
* **Native:** comments live in the WordPress comment tables. Uninstall Convocept and your comments are still there, untouched.
* **Works with page caching:** nothing personal is printed into the page, so full-page caches (WP Rocket, LiteSpeed Cache, Cloudflare and others) can serve it to everyone. Personal details load separately.
* **Works without JavaScript:** the comment form is a real form. If scripts are blocked, readers can still read, reply and post.
* **Private:** no external requests, no tracking, no cookies of its own, no account needed.
* **Accessible:** keyboard friendly, screen-reader announcements, checked against WCAG 2.2 AA in light and dark colours.

= For readers =

* Threaded replies. The comment form moves under the comment you reply to.
* Formatting with a small toolbar: bold, italic, links, code and quotes (a safe subset of Markdown), with a preview.
* Upvotes, and sorting by oldest, newest or top comments.
* Edit your comment for a few minutes after posting, with a countdown.
* "Load more" and "Show more replies", so long discussions stay quick.
* Links to a comment open the right page and highlight it.
* Email me replies to my comment, or all new comments on a post. Or subscribe without commenting.

= For site owners =

* **Subscriptions** replace a separate "subscribe to comments" plugin: double opt-in, one-click unsubscribe, a page where readers manage their subscriptions, and emails sent in the background in batches, so thousands of subscribers never slow down posting.
* **Moderation where you read:** approve, pin, mark as spam or trash comments right in the thread. Pinned comments lead the first page.
* **Spam protection** on top of WordPress and Akismet: a hidden honeypot field, a timing check, a link limit and per-visitor rate limits. The Comments screen shows why a comment was held.
* **Import from Disqus:** upload your Disqus XML export, review a dry run, then import with a progress bar. Replies stay in place, spam and deleted comments are skipped, and running it again never creates duplicates. Large exports (100,000+ comments) run with `wp convocept import disqus`.
* **Search engines:** comments are in the page HTML, described with schema.org structured data. With Yoast SEO or Rank Math, they join the same schema graph.
* **Looks right in your theme:** works with classic and block themes, follows your theme's colours or uses a light or dark palette, and takes your accent colour.
* **Privacy tools:** export and erase subscriptions and votes from WordPress's privacy tools, suggested privacy policy text, and a choice to store commenters' IP addresses in full, anonymized or not at all.
* **Live updates** (optional): new comments appear while the page is open.

= For developers =

* Theme-overridable templates: copy any file from `templates/` to `your-theme/convocept/`.
* CSS custom properties for colours, spacing and radius.
* Actions and filters at every step: rendering, submission, moderation checks, subscriptions, structured data, importers and settings.
* A REST API under `/wp-json/convocept/v1/`, and WP-CLI commands.
* The full hooks reference is on [convocept.com](https://convocept.com).

= Coming next =

Version 1.1 adds importers for Subscribe to Comments Reloaded and wpDiscuz, an Insights dashboard built from your own comment data (no tracking), and a comment moderation screen inside Convocept.

== Installation ==

1. In your WordPress admin, go to Plugins → Add New and search for "Convocept".
2. Install and activate it. Your posts now show the Convocept comment section; existing comments appear right away.
3. Optional: adjust the look, subscriptions and more in Settings → Convocept.
4. Moving from Disqus? Go to Settings → Convocept → Import, or Tools → Import → "Disqus (Convocept)".

Comments are still open or closed per post, and moderated, exactly as set in Settings → Discussion.

== Frequently Asked Questions ==

= Where are my comments stored? =

In the standard WordPress comment tables, exactly as WordPress stores them. Convocept adds a little metadata (such as upvote counts and pins) and two small tables of its own for subscriptions and votes. Nothing is stored outside your site.

= Will it slow down my site? =

No. On page load Convocept adds no scripts and no requests; its styles are inlined next to the thread. The interactive script loads only when a reader gets near the comments. Rendered comment lists are cached, and core's own comment query is skipped.

= Does it work with page caching plugins and CDNs? =

Yes. The cached page contains no personal data, security tokens or pending comments. Logged-in details, saved names and a reader's own votes load separately, after the page is shown.

= How do I move from Disqus? =

In Disqus, open Admin → Moderation → Export and download the file it emails you. In WordPress, go to Settings → Convocept → Import, upload the file and review the dry run before importing. Comments are matched to your posts by the Disqus identifier or the page address, also after a domain change. Big exports can be imported from the command line with `wp convocept import disqus export.xml`.

= Can I import from wpDiscuz or Subscribe to Comments Reloaded? =

Not yet: both importers are planned for version 1.1. Existing comments made through wpDiscuz are already normal WordPress comments, so Convocept shows them right away.

= Do readers need an account? =

No. Guests comment with a name and email address, as in WordPress. Whether registration is required is still set in Settings → Discussion.

= Can readers edit their comments? =

Yes, for a few minutes after posting (5 by default, adjustable, or turned off). Guests can edit from the same browser; logged-in readers from any device. Edits go through the same spam checks.

= Does it work with Akismet and other anti-spam plugins? =

Yes. Comments are posted through WordPress's own comment handling, so Akismet, Antispam Bee and similar plugins check them as usual. Convocept's own checks only ever add protection.

= What happens if JavaScript is disabled? =

Readers see the full thread and a working comment form that posts the normal WordPress way. Reply links, "Show more replies" and pagination work too.

= Does it work with my theme? =

Convocept works with classic themes and block themes. By default it takes your theme's text and background colours; you can also pick a light or dark palette and an accent colour. Themes can restyle it with CSS custom properties or override its templates.

= Is it GDPR friendly? =

Convocept makes no external requests and sets no cookies of its own (WordPress's own "remember me" comment cookies still apply if the reader agrees). Subscriptions use double opt-in, every email has an unsubscribe link, and WordPress's export and erase tools cover subscriptions and votes. You can choose not to store commenters' IP addresses. Settings → Privacy includes suggested policy text.

= Does uninstalling delete my comments? =

No, never. Deleting the plugin removes its temporary data. Settings, subscriptions and votes are kept for a reinstall unless you turn on "Delete all Convocept data when the plugin is deleted" in Settings → Convocept → Advanced.

== Screenshots ==

1. The comment thread on a block theme, following the theme's colours.
2. The dark palette, with threaded replies and upvotes.
3. Replying inline, with the formatting toolbar and preview.
4. Settings → Convocept.
5. Importing from Disqus: the dry run before anything is imported.
6. The page where readers manage their comment subscriptions.

== Changelog ==

= 1.0.0 =
* First release of the rebuilt Convocept: a server-rendered comment thread with no JavaScript on page load.
* Threaded replies, Markdown formatting with preview, editing window, upvotes, sorting, pinned comments.
* Email subscriptions with double opt-in, one-click unsubscribe, background sending and a subscription page. Also a shortcode and a block to subscribe without commenting.
* Front-end moderation tools and extra spam protection.
* Disqus importer with dry run, resumable batches and WP-CLI command.
* Structured data for comments, with Yoast SEO and Rank Math support.
* Settings screen, privacy tools, accessibility and translation support.

== Upgrade Notice ==

= 1.0.0 =
First release of the rebuilt Convocept. Your comments stay in WordPress; nothing to migrate.
