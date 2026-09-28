# Installation

Convocept needs WordPress 6.5 or newer and PHP 7.4 or newer. It works with classic themes and
block themes, and needs no account or outside service.

## Install

1. In wp-admin, go to **Plugins → Add New**, search for **Convocept** and click **Install Now**.
2. Click **Activate**.

That's it. Posts and pages now show the Convocept comment section, with every existing comment.
Nothing is copied or converted: Convocept reads and writes the normal WordPress comment tables.

## What stays with WordPress

Convocept replaces how comments look and how they are posted. These still come from
**Settings → Discussion**, exactly as before:

- whether comments are open, and closing them on old posts;
- whether commenters must be registered or give a name and email;
- moderation rules: hold for approval, the moderation and disallowed-word lists, the link limit;
- avatars.

Akismet, Antispam Bee and similar plugins keep checking every comment, because Convocept posts
comments through WordPress's own comment handling.

## Settings

**Comments → Convocept** has these pages:

| Page | What you set |
|---|---|
| General | Where Convocept shows (post types), default order, comments per page, reply depth, replies shown before "Show more", upvotes, live updates. |
| Appearance | Colour scheme (match the theme, follow the reader's system, light, dark) and accent colour. |
| Composer | How long authors may edit their comment. |
| Moderation & spam | What is checked, and a link to comments awaiting approval. |
| Subscriptions | Email subscriptions, double opt-in, emails sent per batch. |
| Advanced | Trusted proxies (behind a CDN or load balancer), IP addresses stored with comments, structured data, what happens on uninstall. |
| Import | Import from Disqus. |

## Behind a proxy or CDN

Rate limits and spam checks use the visitor's IP address. Behind Cloudflare, a load balancer or
another reverse proxy, every visitor looks like the proxy. Add the proxy's IP ranges under
**Advanced → Trusted proxies** and pick the header it sets (for Cloudflare,
`CF-Connecting-IP`). Convocept only reads that header on requests that really come from a
trusted address.

## Page caching

No setup needed. Pages contain nothing personal, so full-page caches can serve them to everyone.
Logged-in details, saved names and a reader's own votes load after the page is shown.

## Turning it off

Switch off **General → Replace the theme's comments**, or deactivate the plugin: your theme's
comments come back, with every comment. Deleting the plugin never deletes comments; see
**Advanced → Uninstall** for what else is kept.
