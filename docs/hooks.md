# Hooks reference

Every hook Convocept exposes for add-ons and site code. Updated as hooks are added.

## Actions

| Hook | Since | Args | Fires |
|---|---|---|---|
| `convocept_loaded` | 1.0.0 | `Plugin $plugin` | After Convocept registers its hooks on `plugins_loaded`. Add-ons register here. |
| `convocept_activated` | 1.0.0 | — | After activation and migrations. |
| `convocept_deactivated` | 1.0.0 | — | After deactivation cleanup. |
| `convocept_migrated` | 1.0.0 | `int $version, string $class` | After each database migration is applied. |

## Filters

| Hook | Since | Value | Purpose |
|---|---|---|---|
| `convocept_settings_defaults` | 1.0.0 | `array $defaults` | Register default values for add-on settings keys. |
| `convocept_cron_hooks` | 1.0.0 | `string[] $hooks` | WP-Cron hooks cleared on deactivation. Add-ons append theirs. |
