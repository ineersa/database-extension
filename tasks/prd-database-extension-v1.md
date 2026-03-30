# PRD: Database Extension V1

## Introduction/Overview

This feature turns `matesofmate/database-extension` from a template package into a usable Symfony AI Mate extension for safe, read-only database access.

The problem it solves is that Symfony developers using Mate need AI assistants to inspect database structure and run safe investigative queries during development without exposing write access or requiring a separate standalone MCP server. The goal of v1 is to provide a stable, DoctrineBundle-based database extension for real Symfony apps, focused on MySQL, PostgreSQL, and SQLite.

## Goals

- Provide AI assistants with safe, read-only database access inside Symfony AI Mate.
- Support the most common development databases in v1: MySQL, PostgreSQL, and SQLite.
- Integrate with existing DoctrineBundle DBAL connections in the host Symfony application.
- Expose a clear, small capability surface that is easy for Mate to discover and use correctly.
- Ensure the extension is reliable through Docker-based automated tests against real databases.

## User Stories

- As a Symfony developer using Mate, I want the AI assistant to inspect my database schema so it can answer questions about tables, views, and routines.
- As a Symfony developer using Mate, I want the AI assistant to run read-only SQL queries safely so it can help me debug application behavior.
- As a Symfony developer using Mate, I want the extension to use my app's default Doctrine connection automatically when I do not specify a connection.
- As a Symfony developer using Mate, I want the extension to expose nothing when no database integration is available, so the AI is not offered broken tools.
- As a maintainer, I want the extension to be tested against real databases so behavior is trustworthy across supported engines.

## Functional Requirements

1. The system must provide a `database-query` Mate tool for running validated read-only SQL queries.
2. The system must provide a `database-schema` Mate tool for inspecting schema data with summary, columns, and full detail modes.
3. The system must provide a `db://{connection}` Mate resource that returns summary discovery data for tables, views, and routines for a given connection.
4. The `db://{connection}` resource must also include basic connection metadata, including detected platform or driver information.
5. Both tools must accept an optional `connection` parameter.
6. When `connection` is omitted, both tools must use the application's default Doctrine DBAL connection.
7. Tool descriptions must declare the available connections and clearly state which connection is the default.
8. The extension must use the host application's DoctrineBundle DBAL services as the source of database connections.
9. The extension must not require a separate standalone MCP server bootstrap or custom external database configuration file.
10. The extension must register capabilities only when the required Doctrine service layer exists.
11. The extension must avoid exposing broken capabilities when Doctrine integration is entirely unavailable.
12. If Doctrine services exist but a concrete connection is broken, the tools must still be exposed so they can help with debugging.
13. The query path must enforce read-only behavior before query execution by wrapping or reconnecting through the read-only Doctrine driver middleware.
14. The query path must also use a defensive safe execution layer to validate queries and reduce the risk of writes or unsafe operations.
15. The system must preserve TOON output for successful query and schema responses.
16. The tools must return structured MCP results using `CallToolResult` when explicit success or error signaling is needed.
17. Expected operational failures must return structured TOON error payloads with only `error` and `hint`.
18. Tool and resource descriptions must be shorter than the current standalone MCP descriptions.
19. Cross-cutting usage guidance, such as schema-first workflow, row limit guidance, and SQL dialect reminders, must be moved into `INSTRUCTIONS.md`.
20. The extension must include automated tests covering the capability layer, service layer, and real database integration paths.
21. The automated test suite must run through a single Docker-backed PHPUnit command.
22. Integration tests must boot a real Symfony test container with `FrameworkBundle` and `DoctrineBundle`.
23. Integration tests must import the package's real service configuration so the actual extension wiring is exercised.
24. Integration tests must verify default connection fallback, read-only enforcement, schema extraction, and structured error behavior.
25. CI must run the Docker-backed PHPUnit suite for the initial supported databases.

## Non-Goals (Out of Scope)

- PII redaction support in v1.
- SQL Server (`sqlsrv`) support in the initial automated integration matrix.
- A shared reusable package extracted from the database logic.
- Per-table, per-view, or per-routine resources in v1.
- A custom non-Doctrine connection configuration path.
- Full inspector-style MCP snapshot testing for every behavior.

## Design Considerations

- This feature is backend/package-oriented rather than UI-oriented.
- Public names should follow Mate-native conventions: `database-query`, `database-schema`, and `db://{connection}`.
- Descriptions should be concise and optimized for capability selection, while broader workflow guidance belongs in `INSTRUCTIONS.md`.

## Technical Considerations

- The package should depend explicitly on `doctrine/doctrine-bundle`.
- V1 should target Doctrine DBAL 4 first and use the broadest safe DoctrineBundle compatibility range that is actually supported.
- Existing reusable logic should be ported from the standalone MCP into internal services under `src/Service`, `src/Service/Schema`, `src/Enum`, and `src/Exception`.
- Standalone-only concerns such as custom config loading, command bootstrap, transport, and model download services should not be ported.
- Tests should use a minimal Symfony test kernel and real Doctrine DBAL configuration from Docker-provided environment variables.

## Success Metrics

- The extension loads correctly in Symfony applications that already use DoctrineBundle.
- `database-query`, `database-schema`, and `db://{connection}` behave reliably in Docker-based integration tests against MySQL, PostgreSQL, and SQLite.
- Read-only protections prevent write operations in supported test scenarios.
- Default connection fallback works correctly when `connection` is omitted.
- The package can be discovered and used by Mate without requiring a separate standalone MCP setup.

## Open Questions

- What exact DoctrineBundle version range can be supported safely alongside the DBAL 4-first implementation?
- How should capability registration detect Doctrine availability most cleanly at container-build time?
- How much of the standalone MCP test corpus can be ported directly without adding unnecessary maintenance burden?
- When `sqlsrv` is reintroduced after v1, what extra test and compatibility work will be required?
