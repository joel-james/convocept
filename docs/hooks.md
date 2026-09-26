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
| `convocept_comment_created` | 1.0.0 | `int $comment_id, int\|string $approved, Context $context` | After a comment is created through Convocept (REST or our form). |
| `convocept_comment_edited` | 1.0.0 | `WP_Comment $comment` | After a comment is edited through Convocept. |

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
| `convocept_comment_content` | 1.0.0 | `string $html, WP_Comment $comment` | Rendered comment content. |
| `convocept_before_submit` | 1.0.0 | `WP_Error\|null $error, array $data, Context $context` | Return a `WP_Error` to reject a submission (message shown, `status` used). |
| `convocept_moderation_checks` | 1.0.0 | `array<string, callable> $checks` | Moderation checks; each gets `(array $commentdata, Context $context)` and returns a `Decision` or null. |
| `convocept_min_submit_seconds` | 1.0.0 | `float $seconds` | Minimum time between the form appearing and submitting (default 3; 0 disables). |
| `convocept_rate_limit` | 1.0.0 | `int $limit, string $action, int $window` | Adjust or disable (0) a rate limit. |
| `convocept_session_data` | 1.0.0 | `array $data, WP_REST_Request $request` | Session data sent to the script. |
| `convocept_front_config` | 1.0.0 | `array $config` | Static script configuration (never per-visitor). |

## REST routes (`/wp-json/convocept/v1`)

| Route | Method | Access | Purpose |
|---|---|---|---|
| `/session` | GET | public | Who is visiting: login state, name, REST nonce, moderator flag. |
| `/posts/{id}/comments` | GET | public | One page of top-level comments (`page`, `sort`); returns `html`, `page`, `pages`, `total`. |
| `/posts/{id}/comments` | POST | public (core rules apply) | Create a comment; returns `id`, `status`, `html`, `edit_token`, `edit_until`, `total`. |
| `/comments/{id}` | GET | author in window / moderator | Markdown source for the edit form. |
| `/comments/{id}` | PATCH | author in window / moderator | Edit; returns `id`, `status`, `html`. |
| `/preview` | POST | public, rate-limited | Render Markdown; returns `html`. |
| `/comments/{id}/replies` | GET | public (approved) | Replies after `offset`; returns `html`, `remaining`. |
| `/comments/{id}/context` | GET | public (approved) | URL of the page showing the comment; returns `url`. |

## Query variables

- `convocept_sort` — `oldest`, `newest` or `top`.
- `convocept_expand` — top-level comment ID whose whole reply branch is shown.

## Templates

Copy any file from `templates/` to `{theme}/convocept/` to override it: `thread.php`,
`thread-header.php`, `comment.php`, `composer.php`, `pagination.php`. Each template's docblock
lists the `$args` it receives.
