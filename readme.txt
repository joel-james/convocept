=== Convocept – Fast Native Comment System ===
Contributors: joelcj91, foxelabs
Tags: comments, disqus, subscribe to comments, comment form, spam
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast, modern WordPress comments: threaded replies, upvotes, comment subscriptions, spam protection and a Disqus importer. Zero JavaScript on load.

== Description ==

**Convocept is a fast, modern comment system for WordPress that keeps every comment in your own database.** It replaces your theme's comment section with threaded replies, upvotes, email subscriptions and smart spam protection, without loading a single script until your readers reach the comments.

It is a lightweight **Disqus alternative** with no ads, no tracking and no outside service, and a leaner choice than heavy comment plugins such as wpDiscuz. Your comments stay native WordPress comments, so Akismet, backups, exports and the Comments screen keep working, and nothing is lost if you ever switch back.

= ⚡ Built for speed =

* **0 KB of JavaScript on page load.** A tiny script (under 6 KB gzipped) loads only when a reader scrolls near the comments or starts typing.
* **0 extra requests.** Styles are under 3 KB and inlined next to the comments. No fonts, no icons to download, no third-party calls.
* **Server-rendered comments.** Readers and search engines get every visible comment in the page HTML, straight away.
* **No layout shift.** A post with 200 comments scores the same in Lighthouse as the same post with none.
* **Cache friendly.** Nothing personal is printed into the page, so WP Rocket, LiteSpeed Cache, Cloudflare and other page caches can serve it to everyone.

= 💬 A comment section readers enjoy =

* **Threaded comments** with inline replies: the comment form moves under the comment you answer.
* **Formatting toolbar** for bold, italic, links, code and quotes, with a live preview.
* **Upvotes** and sorting by oldest, newest or top comments.
* **Pinned comments** that lead the discussion.
* **Edit after posting** for a few minutes, with a countdown.
* **Load more comments** and **show more replies**, so long discussions stay quick.
* **Comment links** open the right page and highlight the comment.
* **Light, dark or your theme's colours**, with your own accent colour.

= 📬 Comment subscriptions built in =

Replace a separate "subscribe to comments" plugin:

* "Email me replies to my comment" and "Email me all new comments" in the comment form.
* **Subscribe without commenting**, in the thread or anywhere with a block or the `[convocept_subscribe]` shortcode.
* **Double opt-in**, one-click unsubscribe and a page where readers manage their subscriptions.
* Emails go out in the background, in batches, so thousands of subscribers never slow down posting.

= 🛡️ Spam protection and moderation =

* Works with **Akismet**, Antispam Bee and WordPress's own moderation rules.
* Adds a hidden honeypot, a timing check, a link limit and per-visitor rate limits.
* **Moderate in place:** approve, pin, mark as spam or trash comments right in the thread.
* The Comments screen shows why a comment was held.

= 📥 Switch from Disqus in minutes =

Upload your Disqus export, review a **dry run**, then import with a progress bar. Replies stay under their parents, spam and deleted comments are skipped, and running it again never creates duplicates. Comments are matched to your posts by the Disqus identifier or the page address, even after a domain change. Big sites can import 100,000+ comments with WP-CLI: `wp convocept import disqus export.xml.gz`.

Coming from **wpDiscuz**? Your comments are already WordPress comments, so they appear in Convocept the moment you activate it.

= 🔍 Good for SEO =

* Comments are in the page HTML, where search engines can read them.
* **Schema.org structured data** describes each comment and reply.
* Works with **Yoast SEO** and **Rank Math**: comments join their schema graph instead of adding a second one.
* Paginated comments have real links that search engines can follow.

= 🔒 Private and accessible =

* **No external requests, no tracking, no cookies of its own.** No account and no API key.
* WordPress's **export and erase personal data** tools cover subscriptions and votes, and Settings → Privacy gets suggested policy text.
* Choose to store commenters' IP addresses in full, anonymized or not at all.
* **Accessible:** keyboard friendly, announced to screen readers, and checked against WCAG 2.2 AA in light and dark colours.
* **Works without JavaScript:** the comment form is a real form, so readers can always read, reply and post.
* Translation ready, including right-to-left languages.

= 🧩 For developers =

* Override any template from your theme: copy it to `your-theme/convocept/`.
* Restyle with CSS custom properties such as `--convocept-radius` and `--convocept-font`.
* Actions and filters at every step: rendering, submission, spam checks, subscriptions, structured data, importers and settings.
* A REST API and WP-CLI commands.

Documentation and the full hooks reference: [Convocept docs](https://docs.foxelabs.com/software/convocept/getting-started).

= 🗺️ Coming next =

Version 1.1 adds importers for wpDiscuz votes and Subscribe to Comments Reloaded subscribers, an **Insights** dashboard built from your own comment data (no tracking), and a comment moderation screen inside Convocept.

== Installation ==

1. In your WordPress admin, go to **Plugins → Add New**, search for **Convocept** and click **Install Now**.
2. Click **Activate**. Your posts now show the Convocept comment section, with all your existing comments.
3. Optional: adjust colours, subscriptions and more in **Comments → Convocept**.
4. Moving from Disqus? Go to **Comments → Convocept → Import**.

Comments stay open, closed and moderated exactly as set in **Settings → Discussion**.

== Frequently Asked Questions ==

= Is Convocept a good Disqus alternative? =

Yes. It gives you a modern comment section like Disqus, but your comments stay in your own WordPress database, with no ads, no tracking and no third-party scripts. The built-in importer brings over your Disqus comments with their replies, and you can run it again later without creating duplicates.

= How do I migrate from Disqus to WordPress comments? =

In Disqus, open **Admin → Moderation → Export** and download the file it emails you. In WordPress, go to **Comments → Convocept → Import**, upload the file, check the dry run and click **Start import**. For very large exports, use `wp convocept import disqus export.xml.gz`.

= Can I switch from wpDiscuz? =

Yes. wpDiscuz stores normal WordPress comments, so they appear in Convocept straight away: activate Convocept, then deactivate wpDiscuz. Importing wpDiscuz votes and subscriptions is planned for version 1.1.

= Does it replace Subscribe to Comments Reloaded? =

Yes. Convocept has comment subscriptions built in, with double opt-in, one-click unsubscribe and "subscribe without commenting". Deactivate the other plugin so readers don't get two emails. Importing its subscribers is planned for version 1.1.

= Will it slow down my site? =

No. On page load Convocept adds no scripts and no requests; its small stylesheet is inlined next to the comments. The interactive script loads only when a reader reaches the comments. Rendered comments are cached.

= Does it work with caching plugins and CDNs? =

Yes. The cached page contains no personal data or security tokens, so it can be served to everyone. Logged-in names, saved details and a reader's own votes load after the page is shown.

= Where are my comments stored? =

In the standard WordPress comment tables, exactly as WordPress stores them. Convocept adds a little metadata (such as upvote counts and pins) and two small tables for subscriptions and votes. Nothing is stored outside your site.

= Does it work with my theme? =

Convocept works with classic themes and block themes. By default it takes your theme's text and background colours; you can also choose a light or dark palette and an accent colour. Themes can restyle it with CSS custom properties or override its templates.

= Does it work with Akismet? =

Yes. Comments are posted through WordPress's own comment handling, so Akismet, Antispam Bee and similar plugins check every comment as usual. Convocept's own checks only add protection.

= Can readers edit their comments? =

Yes, for a few minutes after posting (5 by default; adjustable or off). Guests can edit from the same browser, logged-in readers from any device. Edits go through the same spam checks.

= Do readers need an account? =

No. Guests comment with a name and email address, as in WordPress. Whether registration is required is still set in Settings → Discussion.

= What happens if JavaScript is disabled? =

Readers see the full comment thread and a working comment form that posts the normal WordPress way. Replies, "show more replies" and pagination work too.

= Is it GDPR friendly? =

Convocept makes no external requests and sets no cookies of its own. WordPress's own "remember me" comment cookies apply only if the reader agrees. Subscriptions use double opt-in, every email has an unsubscribe link, and WordPress's export and erase tools cover subscriptions and votes. You can choose not to store commenters' IP addresses.

= Does uninstalling delete my comments? =

No, never. Your comments are WordPress data. Settings, subscriptions and votes are also kept for a reinstall, unless you turn on "Delete all Convocept data when the plugin is deleted" in Comments → Convocept → Advanced.

== Screenshots ==

1. Threaded comments with upvotes and a pinned comment, in your theme's colours.
2. The dark comment theme, with nested replies.
3. Replying inline, with the formatting toolbar and live preview.
4. Comments → Convocept: all options in one place.
5. Importing comments from Disqus: the dry run shows what will be imported.
6. Readers manage their comment subscriptions on their own page.

== Changelog ==

= 1.0.0 =
* New: rebuilt from the ground up as a server-rendered comment system with zero JavaScript on page load.
* New: threaded comments with inline replies, a formatting toolbar with preview, an edit window, upvotes, sorting and pinned comments.
* New: comment subscriptions with double opt-in, one-click unsubscribe, background sending, a subscription page, a block and a shortcode.
* New: moderation tools in the thread and extra spam protection.
* New: Disqus importer with dry run, resumable batches and a WP-CLI command.
* New: schema.org structured data for comments, with Yoast SEO and Rank Math support.
* New: settings screen, privacy tools, accessibility and translation support.

== Upgrade Notice ==

= 1.0.0 =
The rebuilt Convocept: faster comments, subscriptions and a Disqus importer. Your comments stay in WordPress; nothing to migrate.
