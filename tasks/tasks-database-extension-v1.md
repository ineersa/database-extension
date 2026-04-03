## Relevant Files

- `composer.json` - Add DoctrineBundle and any database-related runtime/test dependencies needed for the extension.
- `config/config.php` - Register the new Mate capabilities and internal services.
- `INSTRUCTIONS.md` - Hold the schema-first workflow guidance, row-limit guidance, and dialect reminders moved out of descriptions.
- `README.md` - Document the actual extension behavior, Doctrine assumptions, and usage.
- `src/Capability/DatabaseTool.php` - Placeholder tool file to be replaced or split into real database capabilities.
- `src/Capability/DatabaseResource.php` - Placeholder resource file to be replaced by the v1 connection summary resource.
- `src/Capability/DatabaseQueryTool.php` - Planned Mate tool for validated read-only SQL queries.
- `src/Capability/DatabaseSchemaTool.php` - Planned Mate tool for schema inspection.
- `src/Capability/ConnectionResource.php` - Planned `db://{connection}` summary resource.
- `src/Service/ConnectionResolver.php` - Planned service for default/named Doctrine connection resolution and metadata listing.
- `src/Service/SafeQueryExecutor.php` - Planned port of the defensive query execution layer.
- `src/ReadOnly/ReadOnlyMiddleware.php` - Planned read-only Doctrine DBAL middleware port.
- `src/ReadOnly/ReadOnlyDriver.php` - Planned read-only Doctrine DBAL driver wrapper.
- `src/ReadOnly/ReadOnlyConnection.php` - Planned read-only Doctrine DBAL connection wrapper.
- `src/Service/DatabaseSchemaService.php` - Planned schema inspection orchestration service.
- `src/Service/Schema/*` - Planned per-database schema inspection helpers.
- `src/Enum/*` - Planned enums for schema detail and matching modes.
- `src/Exception/ToolUsageError.php` - Planned structured expected-error type for tool failures.
- `tests/Capability/*` - Capability-level tests for tools/resources and `CallToolResult` behavior.
- `tests/Unit/*` - Unit tests for validation, safe execution, connection resolution, and schema helpers.
- `tests/Integration/*` - Integration tests using a Symfony test kernel and real DoctrineBundle wiring.
- `tests/Fixtures/App/TestKernel.php` - Planned minimal Symfony kernel for integration tests with DoctrineBundle.
- `tests/Fixtures/App/NoDoctrineKernel.php` - Planned kernel/fixture setup for the “no Doctrine services” registration case.
- `docker-compose.test.yaml` - Planned Docker test stack for MySQL/PostgreSQL/SQLite-backed PHPUnit runs.
- `.github/workflows/ci.yml` - Update CI to run the Docker-backed PHPUnit suite.

### Notes

- All tests are intended to run through one Docker-backed PHPUnit command rather than split database-only and non-database suites.
- As tasks are completed, this file should be updated by changing `- [ ]` to `- [x]`.

## Instructions for Completing Tasks

**IMPORTANT:** As you complete each task, you must check it off in this markdown file by changing `- [ ]` to `- [x]`. This helps track progress and ensures you don't skip any steps.

Update the file after completing each sub-task, not just after completing an entire parent task.

## Tasks

- [ ] 0.0 Create feature branch
  - [ ] 0.1 Create and checkout a new branch for this feature (for example `feature/database-extension-v1`)
- [x] 1.0 Replace the template capability surface with the v1 Mate API
  - [x] 1.1 Remove or replace the placeholder `DatabaseTool` and `DatabaseResource` implementations.
  - [x] 1.2 Add a `database-query` capability class with Mate-native naming and optional `connection` input.
  - [x] 1.3 Add a `database-schema` capability class with Mate-native naming and optional `connection` input.
  - [x] 1.4 Add a `db://{connection}` resource class that returns summary discovery data for tables, views, routines, and basic connection metadata.
  - [x] 1.5 Keep descriptions concise while ensuring they list available connections and clearly identify the default connection.
  - [x] 1.6 Ensure the capability layer returns TOON responses and uses `CallToolResult` where explicit error-state control is required.
- [x] 2.0 Port the core database safety and schema service layer from the standalone MCP
  - [x] 2.1 Port the read-only Doctrine DBAL middleware classes into the extension namespace.
  - [x] 2.2 Port or adapt the safe query execution logic for validated read-only query handling.
  - [x] 2.3 Port the schema service, schema inspectors, enums, and expected-error types needed for summary, columns, and full schema modes.
  - [x] 2.4 Remove standalone-server-only concerns such as custom config loading, transport, command bootstrap, and PII support.
  - [x] 2.5 Normalize expected operational failures to structured TOON payloads containing only `error` and `hint`.
- [x] 3.0 Integrate DoctrineBundle connection resolution and conditional capability registration
  - [x] 3.1 Add a `ConnectionResolver` service that reads named and default DBAL connections from DoctrineBundle services.
  - [x] 3.2 Implement connection metadata discovery for use in tool descriptions and the summary resource.
  - [x] 3.3 Rebuild or wrap resolved connections through the read-only driver path before query or schema work is executed.
  - [x] 3.4 Update `config/config.php` to register the new services and capability classes with autowiring/autoconfiguration.
  - [x] 3.5 Make capability exposure conditional on Doctrine service availability while still allowing broken concrete connections to surface runtime errors for debugging.
- [x] 4.0 Build the Docker-backed Symfony test harness and port the automated tests
  - [x] 4.1 Create a minimal Symfony test kernel with `FrameworkBundle` and `DoctrineBundle` for integration tests.
  - [x] 4.2 Add a no-Doctrine test kernel or equivalent fixture setup to verify that capabilities are not exposed when Doctrine services are absent.
  - [x] 4.3 Add Docker Compose test infrastructure for the initial supported databases: MySQL, PostgreSQL, and SQLite-backed test execution, PHP 8.2.
  - [x] 4.4 Port or adapt unit tests for safe query execution, schema validation, and connection resolution.
  - [x] 4.5 Add capability-level tests covering default connection fallback, structured success/error behavior, and resource payloads.
  - [x] 4.6 Add integration tests that boot the real test container and verify read-only enforcement, schema extraction, and actual extension wiring.
  - [x] 4.7 Ensure the full PHPUnit suite runs through one Docker-backed command.
- [x] 5.0 Update package dependencies, CI, and test tooling for the new extension architecture
  - [x] 5.1 Update `composer.json` with DoctrineBundle and any required DBAL or testing dependencies for the v1 architecture.
  - [x] 5.2 Add or update test scripts so local and CI execution both use the Docker-backed PHPUnit flow.
  - [x] 5.3 Update `.github/workflows/ci.yml` to boot the Docker test stack and run the full PHPUnit suite.
  - [x] 5.4 Verify that the planned dependency ranges remain aligned with the DBAL 4-first implementation target.
- [ ] 6.0 Rewrite developer-facing documentation and Mate instructions for the final v1 behavior
  - [ ] 6.1 Rewrite `README.md` to describe the actual extension capabilities, DoctrineBundle assumptions, supported databases, and read-only guarantees.
  - [ ] 6.2 Rewrite `INSTRUCTIONS.md` to contain the schema-first workflow guidance, row-limit defaults, and dialect-specific reminders moved out of capability descriptions.
  - [ ] 6.3 Document the reduced v1 scope clearly, including the single summary resource and the absence of PII support.
  - [ ] 6.4 Document the Docker-based test workflow so contributors know how to run the full suite locally.
