# Hooks reference

Every hook Convocept exposes for add-ons and site code. Updated as hooks are added.

## Actions

| Hook | Since | Args | Fires |
|---|---|---|---|
| `convocept_loaded` | 1.0.0 | `Plugin $plugin` | After Convocept registers its hooks on `plugins_loaded`. Add-ons register here. |
| `convocept_activated` | 1.0.0 | — | After activation and migrations. |
| `convocept_deactivated` | 1.0.0 | — | After deactivation cleanup. |
| `convocept_migrated` | 1.0.0 | `int $version, string $class` | After each database migration is applied. |
| `convocept_render_thread_header` | 1.0.0 | `Thread $thread` | Top of the thread, before the header. For summaries and notices. |
| `convocept_render_badges` | 1.0.0 | `WP_Comment $comment` | After a comment's badges, in its header. |
| `convocept_render_comment_actions` | 1.0.0 | `WP_Comment $comment` | Inside a comment's action bar, after Reply. |
| `convocept_render_comment_footer` | 1.0.0 | `WP_Comment $comment` | End of a comment, before its replies. |
| `convocept_render_composer_after` | 1.0.0 | `int $post_id` | After the comment form, inside the composer. |

## Filters

| Hook | Since | Value | Purpose |
|---|---|---|---|
| `convocept_settings_defaults` | 1.0.0 | `array $defaults` | Register default values for add-on settings keys. |
| `convocept_cron_hooks` | 1.0.0 | `string[] $hooks` | WP-Cron hooks cleared on deactivation. Add-ons append theirs. |
| `convocept_enabled` | 1.0.0 | `bool $enabled, int $post_id` | Whether Convocept replaces a post's comments. |
| `convocept_thread_query_args` | 1.0.0 | `array $args, int $post_id, string $sort, int $page` | WP_Comment_Query args for a thread page. |
| `convocept_excluded_comment_types` | 1.0.0 | `string[] $types` | Comment types never shown (default pingback, trackback). |
| `convocept_thread_template_args` | 1.0.0 | `array $args, Thread $thread` | Data passed to `thread.php`. |
| `convocept_thread_classes` | 1.0.0 | `string[] $classes, int $post_id` | CSS classes on the thread wrapper. |
| `convocept_comment_view` | 1.0.0 | `array $view, WP_Comment $comment` | View data passed to `comment.php`. |
| `convocept_comment_badges` | 1.0.0 | `array $badges, WP_Comment $comment` | Badges next to the author name (`label`, `type`). |
| `convocept_locate_template` | 1.0.0 | `string $path, string $name` | Resolved path of a template. |

## Templates

Copy any file from `templates/` to `{theme}/convocept/` to override it: `thread.php`,
`thread-header.php`, `comment.php`, `composer.php`, `pagination.php`. Each template's docblock
lists the `$args` it receives.
