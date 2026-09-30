# Hooks reference

Every hook Convocept exposes for add-ons and site code. Updated as hooks are added.

## Actions

| Hook | Since | Args | Fires |
|---|---|---|---|
| `convocept_loaded` | 1.0.0 | `Plugin $plugin` | After Convocept registers its hooks on `plugins_loaded`. Add-ons register here. |
| `convocept_activated` | 1.0.0 | — | After activation and migrations. |
| `convocept_deactivated` | 1.0.0 | — | After deactivation cleanup. |
| `convocept_uninstalled` | 1.0.0 | `bool $delete_data` | After uninstall cleaned up a site (runs per site on multisite). |
| `convocept_migrated` | 1.0.0 | `int $version, string $class` | After each database migration is applied. |
| `convocept_render_thread_header` | 1.0.0 | `Thread $thread` | Top of the thread, before the header. For summaries and notices. |
| `convocept_render_badges` | 1.0.0 | `WP_Comment $comment` | After a comment's badges, in its header. |
| `convocept_render_comment_actions` | 1.0.0 | `WP_Comment $comment` | Inside a comment's action bar, after Reply. |
| `convocept_render_comment_footer` | 1.0.0 | `WP_Comment $comment` | End of a comment, before its replies. |
| `convocept_render_composer_after` | 1.0.0 | `int $post_id` | After the comment form, inside the composer. |
| `convocept_comment_created` | 1.0.0 | `int $comment_id, int\|string $approved, Context $context` | After a comment is created through Convocept (REST or our form). |
| `convocept_comment_edited` | 1.0.0 | `WP_Comment $comment` | After a comment is edited through Convocept. |
| `convocept_reaction_added` | 1.0.0 | `int $comment_id, string $reaction, int $count` | After a visitor upvotes (reaction `up`). |
| `convocept_comment_status_changed` | 1.0.0 | `int $comment_id, string $action` | After front-end moderation (`approve`, `hold`, `spam`, `trash`). |
| `convocept_comment_pinned` | 1.0.0 | `int $comment_id, bool $pinned` | After a comment is pinned or unpinned. |
| `convocept_subscription_confirmed` | 1.0.0 | `int $subscription_id` | After a reader confirms a subscription. |
| `convocept_unsubscribed` | 1.0.0 | `int $subscription_id, bool $all` | After a reader unsubscribes (one or all). |
| `convocept_comment_imported` | 1.0.0 | `int $comment_id, string $source, array $item` | After an importer creates a comment (`$source` e.g. `disqus`). |
| `convocept_import_completed` | 1.0.0 | `Job $job` | When an import finishes (counters in `$job->counts`). |
| `convocept_settings_saved` | 1.0.0 | `array $new, array $old` | After the settings screen saves. Page caches may still hold older pages; purge here. |

## Filters

| Hook | Since | Value | Purpose |
|---|---|---|---|
| `convocept_settings_defaults` | 1.0.0 | `array $defaults` | Register default values for add-on settings keys. |
| `convocept_cron_hooks` | 1.0.0 | `string[] $hooks` | WP-Cron hooks cleared on deactivation. Add-ons append theirs. |
| `convocept_enabled` | 1.0.0 | `bool $enabled, int $post_id` | Whether Convocept replaces a post's comments. |
| `convocept_thread_query_args` | 1.0.0 | `array $args, int $post_id, string $sort, int $page` | WP_Comment_Query args for a thread page. |
| `convocept_excluded_comment_types` | 1.0.0 | `string[] $types` | Comment types never shown (default pingback, trackback). |
| `convocept_thread_template_args` | 1.0.0 | `array $args, Thread $thread` | Data passed to `thread.php`. |
| `convocept_thread_header_args` | 1.0.0 | `array $args, Thread $thread` | Data passed to `thread-header.php` (`thread`, `sort_links`, `follow`). |
| `convocept_thread_classes` | 1.0.0 | `string[] $classes, int $post_id` | CSS classes on the thread wrapper. |
| `convocept_comment_view` | 1.0.0 | `array $view, WP_Comment $comment` | View data passed to `comment.php`. |
| `convocept_comment_badges` | 1.0.0 | `array $badges, WP_Comment $comment` | Badges next to the author name (`label`, `type`). |
| `convocept_locate_template` | 1.0.0 | `string $path, string $name` | Resolved path of a template. |
| `convocept_comment_content` | 1.0.0 | `string $html, WP_Comment $comment` | Rendered comment content. |
| `convocept_before_submit` | 1.0.0 | `WP_Error\|null $error, array $data, Context $context` | Return a `WP_Error` to reject a submission (message shown, `status` used). |
| `convocept_moderation_checks` | 1.0.0 | `array<string, callable> $checks` | Moderation checks; each gets `(array $commentdata, Context $context)` and returns a `Decision` or null. |
| `convocept_min_submit_seconds` | 1.0.0 | `float $seconds` | Minimum time between the form appearing and submitting (default 3; 0 disables). |
| `convocept_rate_limit` | 1.0.0 | `int $limit, string $action, int $window` | Adjust or disable (0) a rate limit. |
| `convocept_session_data` | 1.0.0 | `array $data, WP_REST_Request $request` | Session data sent to the script. |
| `convocept_front_config` | 1.0.0 | `array $config` | Static script configuration (never per-visitor). |
| `convocept_front_modules` | 1.0.0 | `string[] $scripts` | Scripts the inline loader injects after Convocept's (lazy add-on modules). |
| `convocept_inline_css` | 1.0.0 | `string $css` | Inline stylesheet; return `''` to style the thread yourself. |
| `convocept_thread_html` | 1.0.0 | `string $html, int $post_id` | Complete thread HTML (assets are added here). |
| `convocept_fragment_cache` | 1.0.0 | `bool $enabled` | Whether rendered comment lists are cached. |
| `convocept_mailer` | 1.0.0 | `bool\|null $sent, array $message` | Send an email yourself (return true/false) instead of `wp_mail()`. |
| `convocept_should_notify` | 1.0.0 | `bool $notify, WP_Comment $comment` | Whether subscribers hear about a comment (importers return false). |
| `convocept_structured_data_enabled` | 1.0.0 | `bool $enabled, int $post_id` | Whether a post's comments are described in JSON-LD (setting `structured_data`). |
| `convocept_structured_data_comments` | 1.0.0 | `array $items, int $post_id, Thread $thread` | The `Comment` items (replies nested under `comment`); runs after the cache. |
| `convocept_structured_data_node` | 1.0.0 | `array $node, int $post_id` | Standalone article node printed in the footer when no SEO plugin graph carries the comments; `[]` prints nothing. |
| `convocept_importers` | 1.0.0 | `array<string, Importer> $importers` | Add import sources (Tools → Import and `wp convocept import`). |
| `convocept_import_batch_size` | 1.0.0 | `int $limit, string $importer` | Items per import batch (default 500 in wp-admin, `--batch-size` in WP-CLI). |
| `convocept_disqus_thread_post_id` | 1.0.0 | `int $post_id, array $thread` | Post a Disqus thread's comments go into; 0 skips the thread. |
| `convocept_disqus_comment_data` | 1.0.0 | `array $commentdata, array $post` | Comment data before a Disqus comment is inserted; `[]` skips it (e.g. set `user_id`). |
| `convocept_settings_schema` | 1.0.0 | `array $schema` | JSON schema per setting key (type, minimum, maximum, enum, pattern); validates the settings route and sets the screen's limits. |
| `convocept_admin_boot_data` | 1.0.0 | `array $data` | Data the settings app starts with (`window.convoceptAdminBoot`). |

## REST routes (`/wp-json/convocept/v1`)

| Route | Method | Access | Purpose |
|---|---|---|---|
| `/session` | GET | public | Who is visiting: login state, name, REST nonce, moderator flag. |
| `/posts/{id}/comments` | GET | public | One page of top-level comments (`page`, `sort`); returns `html`, `page`, `pages`, `total`. |
| `/posts/{id}/comments` | POST | public (core rules apply) | Create a comment (`token`: the form's signed `convocept_token`; without it the comment is held by the timing check); returns `id`, `status`, `html`, `edit_token`, `edit_until`, `total`, `count_text` (the heading, with the language's plural form). |
| `/comments/{id}` | GET | author in window / moderator | Markdown source for the edit form. Guests send the edit token in an `X-Convocept-Edit-Token` header. |
| `/comments/{id}` | PATCH | author in window / moderator | Edit; returns `id`, `status`, `html`. |
| `/preview` | POST | public, rate-limited | Render Markdown; returns `html`. |
| `/subscriptions` | POST | public, rate-limited | Subscribe without commenting (`email`, `post_id` 0 = site); same answer for every address. |
| `/posts/{id}/updates` | GET | public | Comments newer than `after`; ETag + 304 when unchanged. Returns `items`, `total`, `count_text`. |
| `/comments/{id}/replies` | GET | public (approved) | Replies after `offset`; returns `html`, `remaining`, `more_text` (the "Show N more replies" label, or ''). |
| `/comments/{id}/reactions` | POST | public, rate-limited | `active` true/false toggles the visitor's upvote; returns `count`, `active`. |
| `/comments/{id}/moderate` | POST | `moderate_comments` + `edit_comment` | `action`: approve, hold, spam, trash; returns `status`, `html`. |
| `/comments/{id}/pin` | POST | `moderate_comments` + `edit_comment` | `pinned` true/false; top-level approved comments only. |
| `/posts/{id}/pending` | GET | `moderate_comments` + `edit_post` | Comments awaiting moderation, rendered. |
| `/comments/{id}/context` | GET | public (approved) | URL of the page showing the comment; returns `url`. |
| `/settings` | GET | `manage_options` | Every setting. |
| `/settings` | POST | `manage_options` | `settings`: changed keys only. 400 with `data.fields` (message per key) and nothing saved when any value is invalid; else the complete new settings. |
| `/import` | GET | `import` + `moderate_comments` | Current import job (or null), importers, upload limit. |
| `/import` | POST | `import` + `moderate_comments` | Multipart `file` + `importer`: store the export privately and run the dry run. |
| `/import/batch` | POST | `import` + `moderate_comments` | Import the next batch; returns the job. |
| `/import` | DELETE | `import` + `moderate_comments` | Cancel the import, or clear a finished one. |

## Settings screen (JavaScript)

- `window.convoceptAdmin.registerPanel( { id, group, title, icon, keys, badge, render } )` — add
  a page to Comments → Convocept. `group` is `settings`, `manage` or `more`; `keys` are the
  settings the page edits (used to flag errors in the sidebar; a page with keys shows Save in its
  header); `badge` is an optional `{ count, label }` shown at the end of the sidebar item;
  `render` is a component receiving `{ draft, saved, update, errors, boot }`. Enqueue the script
  with `convocept-admin` as a dependency; pages are read when the DOM is ready.

## WP-CLI

- `wp convocept import disqus <file> [--dry-run] [--batch-size=<n>] [--yes]` — import a Disqus
  export (.xml or .xml.gz); shows the dry run first.
- `wp convocept import resume|status|cancel` — continue, show or forget the current import.

## Query variables

- `convocept_sort` — `oldest`, `newest` or `top`.
- `convocept_expand` — top-level comment ID whose whole reply branch is shown.

## Shortcode and block

- `[convocept_subscribe]` — "subscribe without commenting" for the current post;
  `[convocept_subscribe scope="site"]` for every post; `post_id="123"` for a specific post.
- Block **Comment Subscription** (`convocept/subscribe`) with the same post/site setting.

## Templates

Copy any file from `templates/` to `{theme}/convocept/` to override it: `thread.php`,
`thread-header.php`, `comment.php`, `composer.php`, `pagination.php`, `subscribe-form.php`,
`subscription-manage.php`, `emails/confirm.php`, `emails/confirm-text.php`,
`emails/notification.php`, `emails/notification-text.php`. Each template's docblock
lists the `$args` it receives.
