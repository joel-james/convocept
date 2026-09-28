# Theming

Convocept looks at home in most themes without changes. When you want more control, restyle it
with CSS custom properties first, and override templates only for changes to the markup.

## Colour schemes

**Settings → Convocept → Appearance** sets the scheme. The thread wrapper carries it as
`data-convocept-theme`:

| Setting | `data-convocept-theme` | Colours |
|---|---|---|
| Match the theme (default) | `auto` | Text and background inherited from the theme; borders and muted text are mixed from the text colour, so light and dark themes both work. |
| Follow the reader's system | `system` | Light or dark palette from the reader's `prefers-color-scheme`. |
| Always light | `light` | Built-in light palette on its own background. |
| Always dark | `dark` | Built-in dark palette on its own background. |

The accent colour (links, buttons, focus rings) is set on the same page.

## CSS custom properties

Everything is scoped under `.convocept`. Override tokens, not selectors:

```css
.convocept {
	--convocept-accent: #e11d48;
	--convocept-radius: 4px;
	--convocept-font: Georgia, serif;
}

/* Only the dark palette. */
.convocept[data-convocept-theme="dark"] {
	--convocept-bg: #000;
}
```

| Property | Used for |
|---|---|
| `--convocept-accent` | Buttons, focus rings, selected states. |
| `--convocept-on-accent` | Text on accent-coloured buttons. |
| `--convocept-link` | Links and other accent-coloured text (by default the accent mixed toward the text colour, for contrast). |
| `--convocept-danger` | Spam/Trash actions and error messages. |
| `--convocept-text` | Main text. |
| `--convocept-bg` | Thread background (`transparent` in the `auto` scheme). |
| `--convocept-muted` | Dates, counts, secondary actions. |
| `--convocept-border` | Borders and the reply thread line. |
| `--convocept-surface` | Pinned comments, tabs, subtle panels. |
| `--convocept-field-bg` | Form fields. |
| `--convocept-radius` | Corner radius. |
| `--convocept-gap` | Spacing between comments and around the thread. |
| `--convocept-avatar` | Avatar size. |
| `--convocept-font`, `--convocept-font-size` | Typeface and base size. |

Keep text contrast at 4.5:1 or more when you change colours; the built-in values are checked
against WCAG 2.2 AA.

## Replacing the stylesheet

Convocept inlines its small stylesheet right before the thread (no extra request). A theme that
styles the thread completely can remove it:

```php
add_filter( 'convocept_inline_css', '__return_empty_string' );
```

## Overriding templates

Copy a file from `wp-content/plugins/convocept/templates/` to `your-theme/convocept/` and edit
the copy. Convocept uses the theme's copy from then on (child themes first, then the parent).

| Template | Renders |
|---|---|
| `thread.php` | The whole section: header, form, list, pagination. |
| `thread-header.php` | Comment count and the sort links. |
| `comment.php` | One comment (and, recursively, its replies). |
| `composer.php` | The comment form. It works without JavaScript; keep its `data-convocept-*` attributes, the script relies on them. |
| `pagination.php` | Page links and "Load more comments". |
| `subscribe-form.php` | "Get new comments by email". |
| `subscription-manage.php` | The page where readers manage subscriptions. |
| `emails/*.php` | Confirmation and notification emails (HTML and plain text). |

Each template starts with a comment listing the `$args` it receives. After updating Convocept,
compare your copies with the new originals.

The `convocept_locate_template` filter changes where a template is loaded from.

## Small changes without templates

- `convocept_thread_classes`: add classes to the thread wrapper.
- `convocept_comment_badges`: add badges next to an author name (for example "Member").
- `convocept_comment_view`: change any value a comment template receives.

See the [hooks reference](../hooks.md) for every filter.
