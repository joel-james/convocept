=== Convocept ===
Contributors: joelcj91, foxelabs
Tags: comments, comment system, discussion
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A fast, modern, live-updating interface for native WordPress comments. Self-hosted, no external services, all data stays in your database.

== Description ==

Convocept replaces the default comment interface with a fast, modern thread and composer — without moving a single byte of your data anywhere else. Every comment is stored in the standard WordPress `wp_comments` table exactly as core does it, so your data is always yours and any other plugin that works with comments keeps working.

**What it does**

* Post comments and replies without a page reload, with the new comment appearing instantly.
* Edit your own comment for a few minutes after posting (configurable, logged-in users).
* See new comments arrive while you read, via polling or the WordPress Heartbeat API.
* Works with both block themes and classic themes.

**Progressive enhancement**

The native WordPress comment form and thread are always rendered on the server. The modern interface is layered on top only after it loads successfully. If JavaScript is disabled or the script fails, visitors get the standard WordPress comment experience, fully functional.

**Privacy and self-hosting**

This plugin makes no external HTTP requests. There is no CDN, no external fonts, no analytics, and no phone-home. Comments are never sent to a third-party service.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/convocept` or install it through the Plugins screen.
2. Activate the plugin.
3. Visit Settings > Discussion to configure the modern comments section.

== Frequently Asked Questions ==

= Where are my comments stored? =

In the standard WordPress comment tables, exactly as core stores them. Nothing is stored externally.

= What happens if JavaScript is disabled? =

The native WordPress comment thread and form render and work normally. The modern interface is an enhancement, never a requirement.

= Can guests edit their comments? =

Not in version 1.0. A guest's identity is only recorded in a plain browser cookie, which cannot reliably prove authorship, so editing is limited to logged-in users. Moderators can always edit comments from the WordPress admin.

= Does uninstalling delete my comments? =

No. Uninstalling removes only this plugin's settings and its own comment metadata. Your comments are your site's data and are never deleted.

== Screenshots ==

1. The comment thread and composer on a block theme.
2. The plugin settings on Settings > Discussion.

== Changelog ==

= 1.0.0 =
* Initial release.
