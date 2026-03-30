---
name: database extension v1
overview: Port the existing read-only Doctrine-backed database MCP behavior into the Mate extension as internal services and Mate-native capabilities, using DoctrineBundle and DBAL 4 first, while deferring any shared-package extraction until the API and boundaries stabilize.
todos:
  - id: define-capabilities
    content: Implement the settled Mate-native capability surface with default-connection fallback, TOON output, and a single summary connection resource
    status: pending
  - id: port-service-layer
    content: Port DBAL-centric safety, schema, and read-only connection enforcement from the existing MCP without the standalone bootstrap pieces
    status: pending
  - id: add-connection-resolution
    content: Resolve optional/default Doctrine DBAL connections from DoctrineBundle services and reconnect them through the read-only wrapper before use
    status: pending
  - id: test-and-docs
    content: Port tests, run the full PHPUnit suite in Docker with a minimal Symfony test kernel and DoctrineBundle wiring, update CI, and rewrite README/INSTRUCTIONS around the real database extension
    status: pending
isProject: false
---

# Database Extension V1

## Recommendation

- Keep v1 entirely inside `database-extension`.
- Do **not** copy the standalone MCP host pieces from `/home/ineersa/mcp-servers/mysql-server` (`Command/`, transport, custom YAML bootstrap).
- Port the reusable DBAL logic as internal services, then expose it through Mate-native `#[McpTool]` / `#[McpResource]` classes.
- Skip PII support entirely in v1. That keeps the first release focused on safe read-only querying and schema discovery.
- Use explicit `doctrine/doctrine-bundle` dependency support, optimized for DBAL 4 first.
- Preserve the current MCP behavior where it still fits, but adopt Mate-native public names and a narrower v1 resource surface.

## Why This Direction

- The current package is a real Symfony AI Mate extension scaffold: discovery comes from `extra.ai-mate.scan-dirs` and `extra.ai-mate.includes` in [/home/ineersa/projects/mate/database-extension/composer.json](/home/ineersa/projects/mate/database-extension/composer.json), and services are wired through [/home/ineersa/projects/mate/database-extension/config/config.php](/home/ineersa/projects/mate/database-extension/config/config.php).
- Your existing MCP already separates useful DBAL-heavy logic from host-specific bootstrap. The reusable parts are query safety, schema inspection, enums, and error mapping; the non-reusable parts are the standalone command/bootstrap and custom config loading.
- The extension should assume DoctrineBundle-backed DBAL connections as the host integration model instead of carrying a second connection-configuration path.
- A separate shared package is premature until the Mate-facing API settles. If you extract now, you will likely redesign it immediately once you see how Symfony container-based connection resolution actually wants to work.

## Target Architecture

- Replace the placeholder capabilities in [/home/ineersa/projects/mate/database-extension/src/Capability/DatabaseTool.php](/home/ineersa/projects/mate/database-extension/src/Capability/DatabaseTool.php) and [/home/ineersa/projects/mate/database-extension/src/Capability/DatabaseResource.php](/home/ineersa/projects/mate/database-extension/src/Capability/DatabaseResource.php) with a small set of focused Mate capabilities.
- Public v1 capability surface:
  - `database-query` tool with default-connection fallback and TOON output.
  - `database-schema` tool with default-connection fallback and the existing detail/filter behavior.
  - `db://{connection}` resource returning summary discovery data for tables, views, and routines plus basic connection metadata.
- Tool and resource descriptions should be shorter than the standalone MCP versions.
- Cross-cutting usage guidance such as schema-first workflow, row limits, and dialect reminders should move to [/home/ineersa/projects/mate/database-extension/INSTRUCTIONS.md](/home/ineersa/projects/mate/database-extension/INSTRUCTIONS.md).
- Tool descriptions should still list available connections and clearly state which connection is the default when `connection` is omitted.
- Add internal services under new folders such as:
  - [/home/ineersa/projects/mate/database-extension/src/Service](/home/ineersa/projects/mate/database-extension/src/Service)
  - [/home/ineersa/projects/mate/database-extension/src/Service/Schema](/home/ineersa/projects/mate/database-extension/src/Service/Schema)
  - [/home/ineersa/projects/mate/database-extension/src/Enum](/home/ineersa/projects/mate/database-extension/src/Enum)
  - [/home/ineersa/projects/mate/database-extension/src/Exception](/home/ineersa/projects/mate/database-extension/src/Exception)
- Add a Symfony-specific `ConnectionResolver` service that uses the container/Doctrine registry to:
  - resolve the default DBAL connection when `connection` is omitted
  - resolve a named connection when provided
  - list available connections for descriptions/resources
- Register capabilities when Doctrine services exist, even if a concrete connection is currently broken, so the tools remain available for debugging.
- Do not expose capabilities when no usable Doctrine DBAL service layer is present at all.
- Add a read-only connection wrapper path so resolved connections are reconnected through the ported read-only DBAL middleware before query or schema operations run.
- Keep service APIs framework-agnostic where possible so later extraction is easy:
  - services should work with `Doctrine\DBAL\Connection` and plain PHP arrays
  - only capability classes should know about Mate attributes and MCP result/resource return shapes

## Porting Scope

- Port now from `/home/ineersa/mcp-servers/mysql-server`:
  - query behavior from `src/Tools/QueryTool.php`, including validation rules and connection-aware description generation
  - schema behavior from `src/Tools/SchemaTool.php`, including detail modes, filtering, size guards, and error mapping
  - the discovery role of `src/Resources/ConnectionResource.php`, adapted into a single v1 summary resource that also includes views, routines, and basic metadata
  - read-only connection enforcement from `src/ReadOnly/ReadOnlyMiddleware.php`, `src/ReadOnly/ReadOnlyDriver.php`, and `src/ReadOnly/ReadOnlyConnection.php`
  - safe execution and schema service logic from `src/Service/SafeQueryExecutor.php`, `src/Service/DatabaseSchemaService.php`, and `src/Service/Schema/`
  - small supporting enums/exceptions from `src/Enum/` and `src/Exception/ToolUsageError.php`
- Do **not** port in v1:
  - `DoctrineConfigLoader`
  - `Command/`
  - `Transport/`
  - model download / PII services
  - per-table, per-view, and per-routine resources
  - any env-file or standalone MCP server concerns

## Concrete Implementation Steps

1. Reshape capabilities in [/home/ineersa/projects/mate/database-extension/src/Capability](/home/ineersa/projects/mate/database-extension/src/Capability).

- Replace the placeholder single tool/resource with `database-query`, `database-schema`, and a single `db://{connection}` resource.
- Use Mate-native names while keeping the core query/schema behavior familiar across both implementations.
- Keep tool outputs in TOON and use `CallToolResult` directly where error-state control is needed.
- For expected operational failures, return structured TOON error payloads with `error` and `hint`.
- Prefer multiple focused capability classes over one large `DatabaseTool.php`; this matches your existing MCP more closely than the simpler Boost-style pattern.

1. Introduce internal query and schema services.

- Copy/adapt the DBAL-safe logic into Mate package namespaces.
- Remove custom config-loading concerns and other standalone-server dependencies.
- Port the read-only DBAL middleware and wrap resolved connections so the runtime connection used by tools/resources is always the protected one.
- Keep `SafeQueryExecutor` as defense in depth behind the read-only wrapper.

1. Add Symfony connection resolution.

- Implement optional connection selection with sensible default fallback from the Doctrine registry/container.
- Make connection listing reusable by both tool descriptions and resources.
- Ensure the resolver can list all configured connections, identify the default one, and rebuild or wrap the selected connection with the read-only driver before use.

1. Wire everything in [/home/ineersa/projects/mate/database-extension/config/config.php](/home/ineersa/projects/mate/database-extension/config/config.php).

- Register all new capability and service classes.
- Keep autowiring/autoconfiguration as the default path.

1. Replace placeholder tests with behavior tests.

- Run the entire PHPUnit suite in Docker rather than splitting database-only tests from non-database tests.
- Add a minimal Symfony test application/kernel for integration tests that:
  - boots a real container with `FrameworkBundle` and `DoctrineBundle`
  - imports the package's real [/home/ineersa/projects/mate/database-extension/config/config.php](/home/ineersa/projects/mate/database-extension/config/config.php)
  - configures Doctrine DBAL connections from Docker-provided environment variables
- Use `KernelTestCase`-style integration tests to fetch real services from the test container and exercise the actual extension wiring.
- Add a dedicated no-Doctrine test kernel or equivalent fixture setup to verify that capabilities are not exposed when Doctrine services are absent.
- Expand [/home/ineersa/projects/mate/database-extension/tests/Capability](/home/ineersa/projects/mate/database-extension/tests/Capability) and/or new `tests/Integration` coverage for query validation, optional/default connection resolution, schema detail modes, resource shapes, and conditional capability registration.
- Add unit tests for the ported service layer where the safety logic is non-trivial.
- Port relevant test cases from the standalone MCP so tool/resource behavior remains aligned.
- Add tests that lock down `CallToolResult` success/error behavior in the Mate integration path.
- Add Docker Compose-based integration coverage for the initial supported databases so read-only enforcement and schema extraction are verified against real engines.
- Update `.github/workflows/ci.yml` so GitHub Actions boots the Docker test stack and runs a single PHPUnit command for the full suite.

1. Update extension docs.

- Rewrite [/home/ineersa/projects/mate/database-extension/README.md](/home/ineersa/projects/mate/database-extension/README.md) around actual capabilities, DoctrineBundle assumptions, and read-only guarantees.
- Update [/home/ineersa/projects/mate/database-extension/INSTRUCTIONS.md](/home/ineersa/projects/mate/database-extension/INSTRUCTIONS.md) with the moved workflow guidance: inspect schema first, default row limits, and database-specific query reminders.

## Design Rules To Preserve Extraction Later

- Do not let service classes depend on Mate attributes, resource DTO shapes, or Symfony console classes.
- Keep `ConnectionResolver` as the only Symfony/container-aware adapter.
- Keep query policy, schema inspection, and error mapping in plain PHP services.
- If another repo needs the same DBAL logic later, extract those services as a shared package after v1 stabilizes, rather than before.

## Expected Outcome

- `database-extension` becomes a genuine Mate extension instead of a template fork.
- The package preserves the core database MCP behavior while fitting the Symfony AI Mate extension system and naming conventions.
- The package ships quickly with the highest-value database features from your existing MCP.
- The codebase stays extraction-friendly without paying the complexity cost of a new shared package yet.
