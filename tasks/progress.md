# Database Extension V1 Progress

Last updated: 2026-03-31

## Current Status

- Task `1.0 Replace the template capability surface with the v1 Mate API` is complete.
- Subtasks `1.1` through `1.6` are complete and checked in `tasks/tasks-database-extension-v1.md`.
- Task `2.0 Port the core database safety and schema service layer from the standalone MCP` is complete.
- Subtasks `2.1` through `2.5` are complete and checked in `tasks/tasks-database-extension-v1.md`.
- Task `3.0 Integrate DoctrineBundle connection resolution and conditional capability registration` is complete.
- Subtasks `3.1` through `3.5` are complete and checked in `tasks/tasks-database-extension-v1.md`.
- Task `4.0 Build the Docker-backed Symfony test harness and port the automated tests` is complete.
- Subtasks `4.1` through `4.7` are complete and checked in `tasks/tasks-database-extension-v1.md`.
- Task `5.0 Update package dependencies, CI, and test tooling for the new extension architecture` is complete.
- Subtasks `5.1` through `5.4` are complete and checked in `tasks/tasks-database-extension-v1.md`.

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

## Task 3 Completed Work

- Added Symfony/container-aware Doctrine DBAL adapter service:
  - `src/Service/ConnectionResolver.php`
  - Resolves default and named connections from DoctrineBundle service layer.
  - Rebuilds resolved connections through read-only middleware before query/schema execution.
  - Lists connection metadata (`driver`, `platform`, `server_version`) for discovery use.
- Wired capabilities to execute against resolved Doctrine DBAL connections:
  - `src/Capability/DatabaseQueryTool.php`
    - Uses `ConnectionResolver` + `SafeQueryExecutor` for real read-only query execution.
  - `src/Capability/DatabaseSchemaTool.php`
    - Uses `ConnectionResolver` + `DatabaseSchemaService` for real schema extraction.
  - `src/Capability/ConnectionResource.php`
    - Uses resolved connection metadata and real schema/routine discovery data.
- Updated service registration and capability exposure guard:
  - `config/config.php`
  - Registers `ConnectionResolver` and database capabilities only when DoctrineBundle service layer classes are available.
- Expanded tests for the new connection-resolution and wiring path:
  - `tests/Unit/ConnectionResolverTest.php` (new)
  - `tests/Capability/DatabaseQueryToolTest.php` (updated)
  - `tests/Capability/DatabaseSchemaToolTest.php` (updated)
  - `tests/Capability/ConnectionResourceTest.php` (updated)

## Task 4 Completed Work

- Added Symfony integration-test kernels and fixtures:
  - `tests/Fixtures/App/TestKernel.php`
    - Boots `FrameworkBundle` + `DoctrineBundle`.
    - Imports package `config/config.php` for real extension wiring.
    - Configures DBAL connections for SQLite (default), MySQL, and PostgreSQL.
  - `tests/Fixtures/App/NoDoctrineKernel.php`
    - Boots `FrameworkBundle` only.
    - Imports package `config/config.php` to verify capability non-exposure without Doctrine bundle wiring.
  - `tests/Fixtures/Database/DatabaseTestFixtures.php`
    - Cross-database fixture reset utility for users table + active_users view.
- Added integration coverage for real container wiring and runtime behavior:
  - `tests/Integration/DoctrineIntegrationTest.php`
    - Verifies capability service exposure with Doctrine enabled.
    - Verifies default connection fallback in `database-query`.
    - Verifies structured tool error behavior for unknown connections.
    - Verifies read-only enforcement against live connections.
    - Verifies schema extraction and `db://{connection}` resource payload shape against real DBAL schema manager.
  - `tests/Integration/NoDoctrineKernelTest.php`
    - Verifies database capabilities are not exposed when Doctrine bundle integration is absent.
- Expanded unit coverage for service-layer validation and execution behavior:
  - `tests/Unit/SafeQueryExecutorTest.php` (extended)
    - Added multiple-statement rejection assertions.
    - Added multi-row truncation and single-row preservation assertions for long text values.
  - `tests/Unit/DatabaseSchemaServiceTest.php` (new)
    - Added detail/match-mode validation assertions.
    - Added SQLite summary extraction assertion.
- Updated package config guard to use container-level Doctrine service-layer detection:
  - `config/config.php`
  - Database capabilities now register only when Doctrine integration is actually present in the container context.

## Docker Test Harness

- Added Docker matrix infrastructure and scripts:
  - `Dockerfile.test`
  - `docker-compose.test.yaml`
  - `tests/bin/run-container-phpunit.sh`
  - `tests/bin/run-docker-phpunit.sh`
- The Docker-backed test command runs both variants:
  - PHP 8.2 + Symfony `^7.3`
  - PHP 8.4 + Symfony `^8.0`
- Added composer script:
  - `composer test:docker`
- Updated composer test and quality scripts for PHP 8.2 container-first workflow:
  - `composer test` now runs the full suite in Docker on PHP 8.2 + Symfony `^7.3`.
  - `composer lint` now runs both local linting and a PHP 8.2 Docker lint pass.
  - `composer fix` now runs local fixers and also executes a PHP 8.2 Docker fixer pass.
  - `composer coverage` now runs in the PHP 8.2 Docker container with Xdebug and writes reports to host `coverage/`.
  - Added helper scripts:
    - `tests/bin/run-docker-coverage.sh`
    - `tests/bin/run-docker-quality.sh`
  - Added local-only convenience scripts:
    - `composer test:local`
    - `composer coverage:local`
    - `composer lint:local`
    - `composer fix:local`
    - `composer lint:php82`
    - `composer fix:php82`
- Updated `Dockerfile.test` to install and enable Xdebug for Docker coverage collection.
- Added ignore rule for generated app-only config reference file:
  - `.gitignore` now includes `config/reference.php`.

## Task 5 Completed Work

- Updated `.github/workflows/ci.yml` to use the Docker-backed test flow:
  - Lint job runs `tests/bin/run-docker-quality.sh lint` (PHP 8.2 Docker container, no database dependencies).
  - Test job uses a `fail-fast: false` matrix with two entries:
    - PHP 8.2 / Symfony ^7.3 (`run-docker-phpunit.sh php82`)
    - PHP 8.4 / Symfony ^8.0 (`run-docker-phpunit.sh php84`)
  - Both entries reuse the existing Docker scripts that handle database service startup, container build, test execution, and cleanup.
  - Removed the previous host-PHP-based lint and test jobs.
- Verified dependency ranges against the DBAL 4-first implementation target:
  - `doctrine/dbal: ^4.0` — correctly enforces DBAL 4 only (lock resolves to 4.4.3).
  - `doctrine/doctrine-bundle: ^2.14` (dev) — supports DBAL 4 (`^3.7.0 || ^4.0`) and is broad enough to include Symfony 8-compatible releases.
  - `symfony/framework-bundle: ^7.3 || ^8.0` (dev) — covers the full CI matrix.
  - `php: >=8.2` — covers both matrix PHP versions (8.2, 8.4).
  - No direct DoctrineBundle class imports in source code — all interaction is service-container-based.
- Added `suggest` section to `composer.json` hinting at `doctrine/doctrine-bundle` for users who install the extension without it.

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
- `composer coverage` passes and writes reports to `coverage/`.
- `bash tests/bin/run-docker-phpunit.sh matrix` passes (PHP 8.2/Symfony ^7.3 and PHP 8.4/Symfony ^8.0 matrix).
