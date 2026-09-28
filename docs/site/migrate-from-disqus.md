# Migrate from Disqus

Convocept imports a Disqus export into native WordPress comments: authors, dates, replies and
all. Nothing is imported until you have reviewed a dry run, and importing the same export again
never creates duplicates.

## 1. Export from Disqus

In Disqus, open **Admin → Moderation → Export** (or **Community → Export**) and start an export.
Disqus emails you a link to a `.xml.gz` file, usually within minutes; large sites can take longer.
You can upload the `.xml.gz` as it is.

## 2. Import in WordPress

1. Go to **Settings → Convocept → Import** (or **Tools → Import → Disqus (Convocept)**).
2. Choose the export file and click **Upload and analyse**.
3. Review the **dry run**: how many comments will be imported, how many belong to pages found
   on your site, and how many are spam or deleted (those are skipped). A sample shows the first
   comments as they will be imported.
4. Click **Start import**. A progress bar shows each batch. You can close the page: come back
   and click **Continue**, and the import picks up where it stopped.

When it finishes you get a report of what was imported and what was skipped, and why.

## Large exports: WP-CLI

The upload size is limited by your server (shown on the Import page). For bigger exports, or to
import 100,000+ comments quickly, use WP-CLI:

```bash
wp convocept import disqus disqus-export.xml.gz
```

It shows the dry run first and asks before importing. Options: `--dry-run` (stop after the
analysis), `--batch-size=<n>`, `--yes` (don't ask). If it is interrupted, run
`wp convocept import resume`. `wp convocept import status` and `wp convocept import cancel` show
or forget the current import.

On a test machine, 98,000 comments imported in about two minutes, and running the same export
again took 11 seconds and added nothing.

## How comments are matched to posts

Each Disqus thread is matched to a post by, in order:

1. the Disqus identifier, when it is a WordPress post ID (the Disqus plugin sets `123 https://…`
   style identifiers);
2. the thread's URL, also when your domain or permalinks have changed since.

Threads that match no post are skipped and counted in the report. Developers can decide the post
themselves with the `convocept_disqus_thread_post_id` filter.

## What is imported

- Author name and email, the comment, the date, and the author's IP address (following your
  **Advanced → IP address** setting).
- Replies stay under their parent. A reply to a deleted or spam comment moves up to the nearest
  comment that was kept.
- Imported comments are approved or held as they were in Disqus.
- Spam and deleted comments are skipped.
- Imported comments **don't email your subscribers**.

## Comments already on your site

Comments the Disqus WordPress plugin already synced into WordPress, and comments from an earlier
import, are recognised and skipped. You can run the import again after new comments arrive in
Disqus; only the new ones are added.

## After the import

Deactivate the Disqus plugin, so your theme stops loading Disqus. Your comments are now served
from your own database, with no Disqus scripts, ads or tracking.
