# Changelog

All notable changes to Convocept. The format follows [Keep a Changelog](https://keepachangelog.com/),
and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased] — 2.0.0

### Added
- M0 scaffolding: PSR-4 autoloading via Composer, `Plugin::boot()`, activation/deactivation
  handlers, versioned database migration runner with a concurrency lock.
- Settings service reading the single `convocept_settings` option, with a
  `convocept_settings_defaults` filter for add-ons.
- Hooks: `convocept_loaded`, `convocept_activated`, `convocept_deactivated`, `convocept_migrated`,
  `convocept_cron_hooks`.
- Tooling: esbuild front-end build, `@wordpress/scripts` admin build, size-limit budgets
  (loader 1 KB, JS 8 KB, CSS 5 KB gzipped), phpcs (WPCS + PHPCompatibility), PHPStan level 6,
  ESLint, Prettier, stylelint, wp-env, PHPUnit, Playwright, GitHub Actions CI, release zip script.

### Changed
- Migration 1 renames the prototype's `_convocept_edited` comment meta to `convocept_edited_at` and
  drops prototype-only settings (`live_updates`, `poll_interval`, `load_styles`).

### Removed
- The 1.0.0 React prototype front end. Until M1 lands, the theme's default comments render.

## [1.0.0]
- Prototype release approved on WordPress.org to reserve the `convocept` slug.
