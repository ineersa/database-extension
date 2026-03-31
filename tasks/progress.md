# Database Extension V1 Progress

Last updated: 2026-03-30

## Current Status

- Task `1.0 Replace the template capability surface with the v1 Mate API` is complete.
- Subtasks `1.1` through `1.6` are complete and checked in `tasks/tasks-database-extension-v1.md`.
- Task `2.0 Port the core database safety and schema service layer from the standalone MCP` is complete.
- Subtasks `2.1` through `2.5` are complete and checked in `tasks/tasks-database-extension-v1.md`.

## What Was Completed

- Replaced placeholder capabilities with v1 Mate-native capability classes:
  - `database-query`
  - `database-schema`
  - `db://{connection}` (resource template)
- Updated service wiring in `config/config.php` to register new capabilities.
- Replaced template capability tests with v1 capability tests.
- Added `helgesverre/toon` dependency with version constraint `^3.0`.
- Added `doctrine/dbal` dependency with version constraint `^4.0` for the ported service layer.

## Task 2 Completed Work

- Ported read-only DBAL middleware layer:
  - `src/ReadOnly/ReadOnlyMiddleware.php`
  - `src/ReadOnly/ReadOnlyDriver.php`
  - `src/ReadOnly/ReadOnlyConnection.php`
- Ported and adapted safe query execution logic:
  - `src/Service/SafeQueryExecutor.php`
  - Includes read-only validation rules, write-keyword blocking, single-statement enforcement, and sandboxed execution rollback flow.
- Ported schema service and per-driver schema inspectors for supported v1 engines:
  - `src/Service/DatabaseSchemaService.php`
  - `src/Service/Schema/DriverSchemaInspectorInterface.php`
  - `src/Service/Schema/SchemaInspectorFactory.php`
  - `src/Service/Schema/SchemaObjectNameExtractorTrait.php`
  - `src/Service/Schema/MysqlSchemaInspector.php`
  - `src/Service/Schema/PostgreSqlSchemaInspector.php`
  - `src/Service/Schema/SqliteSchemaInspector.php`
- Ported supporting enums and expected-error type:
  - `src/Enum/SchemaDetail.php`
  - `src/Enum/SchemaMatchMode.php`
  - `src/Exception/ToolUsageError.php`
- Updated capability error normalization:
  - `src/Capability/DatabaseQueryTool.php`
  - `src/Capability/DatabaseSchemaTool.php`
  - Expected operational failures map to TOON payloads containing only `error` and `hint`.
- Registered service-layer classes in `config/config.php` for autowiring.
- Added unit coverage for read-only query validation:
  - `tests/Unit/SafeQueryExecutorTest.php`

## Scope Guardrails Applied

- Standalone-server-only pieces were not ported in Task 2:
  - No command bootstrap/transport wiring
  - No custom standalone config loader path
  - No PII service path

## TOON Note

- TOON output now uses `HelgeSverre\Toon\Toon::encode()` directly.
- No custom `ToonEncoder` implementation is used.

## Validation

- `composer test` passes.
- `composer lint` passes.
