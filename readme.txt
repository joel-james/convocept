=== Convocept – Fast Native Comment System ===
Contributors: joelcj91, foxelabs
Tags: comments, disqus, subscribe to comments, comment form, spam
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast, modern WordPress comments: threaded replies, upvotes, comment subscriptions, spam protection and a Disqus importer. Zero JavaScript on load.

== Description ==

**[Convocept](https://convocept.com) is a fast, modern comment system for WordPress that keeps every comment in your own database.** It replaces your theme's comment section with threaded replies, upvotes, email subscriptions and smart spam protection, and it loads no script at all until your readers reach the comments.

It is a lightweight **Disqus alternative** with no ads, no tracking and no outside service. Your comments stay native WordPress comments, so Akismet, backups, exports and the Comments screen keep working, and nothing is lost if you ever switch back.

Everything described here is free and fully working. See it in action and read the guides at **[convocept.com](https://convocept.com)**.

= ⚡ Built for speed =

* **0 KB of JavaScript on page load.** A small script (about 6 KB gzipped) loads only when a reader scrolls near the comments or starts typing.
* **0 extra requests.** Styles are under 4 KB gzipped and inlined next to the comments. No fonts, no icons to download, no third-party scripts.
* **Server-rendered comments.** Readers and search engines get every visible comment in the page HTML, straight away.
* **No layout shift.** In our tests a post with 200 comments scored 99–100 in Lighthouse, against 100 for the same post with no comments.
* **Cache friendly.** Nothing personal is printed into the page, so WP Rocket, LiteSpeed Cache, Cloudflare and other page caches can serve it to everyone.

How that compares with Disqus, wpDiscuz and default WordPress comments: [Convocept comparisons](https://convocept.com/compare/).

= 💬 A comment section readers enjoy =

* **Threaded comments** with inline replies: the comment form moves under the comment you answer.
* **Formatting toolbar** for bold, italic, links, code and quotes, with a preview.
* **Upvotes** and sorting by oldest, newest or top comments.
* **Pinned comments** that lead the discussion.
* **Edit after posting** for a few minutes, with a countdown.
* **Load more comments** and **show more replies**, so long discussions stay quick.
* **Comment links** open the right page and highlight the comment.
* **A clean design that fits any theme**: light or dark, your theme's font and your own accent colour.

The full list, with screenshots: [Convocept features](https://convocept.com/features/).

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

Step-by-step guides: [Migrate to Convocept](https://convocept.com/migrate/). Side by side: [Convocept vs Disqus](https://convocept.com/compare/disqus/) and [Convocept vs wpDiscuz](https://convocept.com/compare/wpdiscuz/).

= 🔍 Good for SEO =

* Comments are in the page HTML, where search engines can read them.
* **Schema.org structured data** describes each comment and reply.
* Works with **Yoast SEO** and **Rank Math**: comments join their schema graph instead of adding a second one.
* Paginated comments have real links that search engines can follow.

= 🔒 Private and accessible =

* **No external requests, no tracking, no cookies of its own**, except WordPress's Gravatar avatars when **Show Avatars** is on in Settings → Discussion (turn it off and Convocept shows initials). No account and no API key.
* WordPress's **export and erase personal data** tools cover subscriptions and votes, and Settings → Privacy gets suggested policy text.
* Choose to store commenters' IP addresses in full, anonymized or not at all.
* **Accessible:** keyboard friendly, announced to screen readers, and checked against WCAG 2.2 AA in light and dark colours.
* **Works without JavaScript:** the comment form is a real form, so readers can always read, reply and post.
* Translation ready, including right-to-left languages.

= 🧩 For developers =

* Override any template from your theme: copy it to `your-theme/convocept/`.
* Restyle with CSS custom properties such as `--convocept-radius` and `--convocept-font`.
* Actions and filters at every step: rendering, submission, spam checks, subscriptions, structured data, importers and settings.
* A REST API and a WP-CLI import command.

Guides and the full hooks reference are in the [Convocept documentation](https://convocept.com/docs/).

= 🗺️ Coming next =

Version 1.1 adds importers for wpDiscuz votes and Subscribe to Comments Reloaded subscribers, an **Insights** dashboard built from your own comment data (no tracking), and a comment moderation screen inside Convocept. Follow along on the [Convocept roadmap](https://convocept.com/roadmap/).

= 🌐 Learn more =

* [Convocept website](https://convocept.com): features, comparisons and news.
* [Documentation](https://convocept.com/docs/): installation, settings, theming and hooks.
* [Migration guides](https://convocept.com/migrate/): from Disqus, wpDiscuz and default WordPress comments.
* [Support](https://convocept.com/support/): the support forum, migration help and email.
* [Blog](https://convocept.com/blog/): news and release posts.

== Installation ==

1. In your WordPress admin, go to **Plugins → Add New**, search for **Convocept** and click **Install Now**.
2. Click **Activate**. Your posts now show the Convocept comment section, with all your existing comments.
3. Optional: adjust colours, subscriptions and more in **Comments → Convocept**.
4. Moving from Disqus? Go to **Comments → Convocept → Import**.

Comments stay open, closed and moderated exactly as set in **Settings → Discussion**.

A walkthrough of every setting is in the [getting started guide](https://convocept.com/docs/).

== Frequently Asked Questions ==

= Is Convocept a good Disqus alternative? =

Yes. It gives you a modern comment section like Disqus, but your comments stay in your own WordPress database, with no ads, no tracking and no third-party scripts. The built-in importer brings over your Disqus comments with their replies, and you can run it again later without creating duplicates. See the full [Convocept vs Disqus comparison](https://convocept.com/compare/disqus/).

= How do I migrate from Disqus to WordPress comments? =

In Disqus, open **Admin → Moderation → Export** and download the file it emails you. In WordPress, go to **Comments → Convocept → Import**, upload the file, check the dry run and click **Start import**. For very large exports, use `wp convocept import disqus export.xml.gz`. The [migration guide](https://convocept.com/migrate/) walks through each step.

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

Convocept works with classic themes and block themes. It draws its own simple design, so it looks the same in every theme, and takes only your theme's font. Choose light, dark or the reader's device setting, and an accent colour. Themes can restyle it with CSS custom properties or override its templates.

= Does it work with Akismet? =

Yes. Comments are posted through WordPress's own comment handling, so Akismet, Antispam Bee and similar plugins check every comment as usual. Convocept's own checks only add protection.

= Can readers edit their comments? =

Yes, for a few minutes after posting (5 by default; adjustable or off). Guests can edit from the same browser, logged-in readers from any device. Edits go through the same spam checks.

= Do readers need an account? =

No. Guests comment with a name and email address, as in WordPress. Whether registration is required is still set in Settings → Discussion.

= What happens if JavaScript is disabled? =

Readers see the full comment thread and a working comment form that posts the normal WordPress way. Replies, "show more replies" and pagination work too.

= Is it GDPR friendly? =

Convocept makes no external requests and sets no cookies of its own. The one exception is WordPress's Gravatar avatars: with **Show Avatars** on in Settings → Discussion (the WordPress default), readers' browsers load avatar images from Gravatar. Turn it off and Convocept shows initials instead, with no outside request at all. WordPress's own "remember me" comment cookies apply only if the reader agrees. After commenting, the reader's browser keeps a short-lived code that lets them edit their comment; it is never sent anywhere else. Subscriptions use double opt-in, every email has an unsubscribe link, and WordPress's export and erase tools cover subscriptions and votes. You can choose not to store commenters' IP addresses.

= Does uninstalling delete my comments? =

No, never. Your comments are WordPress data. Settings, subscriptions and votes are also kept for a reinstall, unless you turn on "Delete all Convocept data when the plugin is deleted" in Comments → Convocept → Advanced.

= Where can I get help? =

Ask in the free WordPress.org support forum for this plugin. The [Convocept support page](https://convocept.com/support/) lists the other ways to reach us, including migration help, and the [documentation](https://convocept.com/docs/) covers every setting.

== Screenshots ==

1. Threaded comments with upvotes and a pinned comment.
2. The dark comment theme, with nested replies.
3. Replying inline, with the formatting toolbar and preview.
4. Comments → Convocept: settings that look like WordPress, in sections for General, Appearance, Comment form, Spam & moderation and Advanced.
5. Importing comments from Disqus: the dry run shows what will be imported.
6. Readers manage their comment subscriptions on their own page.

== Changelog ==

= 1.0.1 – 2026-10-13 =
* Fix: on PHP 7.4 to 8.1, links, web addresses and inline code in comments written with the Convocept comment form showed as a number instead, and PHP logged a "Null byte in regex" warning. They display correctly again after updating; nothing needs repairing.
* Readme: links to the Convocept website, documentation, migration guides and support.

= 1.0.0 – 2026-10-13 =
* First release: a server-rendered comment system with zero JavaScript on page load.
* New: threaded comments with inline replies, a formatting toolbar with preview, an edit window, upvotes, sorting and pinned comments.
* New: comment subscriptions with double opt-in, one-click unsubscribe, background sending, a subscription page, a block and a shortcode.
* New: moderation tools in the thread and extra spam protection.
* New: Disqus importer with dry run, resumable batches and a WP-CLI command.
* New: schema.org structured data for comments, with Yoast SEO and Rank Math support.
* New: settings screen, privacy tools, accessibility and translation support.

== Upgrade Notice ==

= 1.0.1 =
Fixes links and inline code in comments on PHP 7.4 to 8.1. Recommended for every site on PHP older than 8.2.

= 1.0.0 =
First release: fast comments, subscriptions and a Disqus importer. Your comments stay in WordPress; nothing to migrate.
