# Changelog

For historical changes before this package was extracted, see the
[`deployer-wordpress` changelog](https://github.com/gaambo/deployer-wordpress/blob/main/CHANGELOG.md).

## Unreleased

### Breaking Changes

- Remove `Utils::quote()`. Use Deployer's `quote()` directly; this package requires
  Deployer 8, where the old Deployer 7 compatibility wrapper is no longer needed.
- Remove the `Utils::parseStringArray()` and `Utils::parseStringOrNull()` helpers.
  Their config-specific behavior now lives in `Rsync`.

### Fixed

- Fix `dep config` crashing with "Unsupported value type" when a runtime is
  configured. The `runtime()` helper now returns a plain definition array
  instead of a `Runtime` object, keeping host config serializable.

## 0.1.0

Initial public `0.x` release.

### Added

- Add shared Composer, npm, file, rsync, utility, and localhost helpers.
- Add native and DDEV runtimes for local and remote Deployer hosts.
- Add opt-in `files:backup:remote` and `files:backup:local` tasks.
- Ship reusable test support under `tests/`.
