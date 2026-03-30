# Database Extension V1 Progress

Last updated: 2026-03-30

## Current Status

- Task `1.0 Replace the template capability surface with the v1 Mate API` is complete.
- Subtasks `1.1` through `1.6` are complete and checked in `tasks/tasks-database-extension-v1.md`.

## What Was Completed

- Replaced placeholder capabilities with v1 Mate-native capability classes:
  - `database-query`
  - `database-schema`
  - `db://{connection}` (resource template)
- Updated service wiring in `config/config.php` to register new capabilities.
- Replaced template capability tests with v1 capability tests.
- Added `helgesverre/toon` dependency with version constraint `^3.0`.

## TOON Note

- TOON output now uses `HelgeSverre\Toon\Toon::encode()` directly.
- No custom `ToonEncoder` implementation is used.

## Validation

- `composer test` passes.
- `composer lint` passes.
