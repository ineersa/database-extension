# Database Extension for Symfony AI Mate

Safe, read-only database access for AI assistants running inside [Symfony AI Mate](https://symfony.com/doc/current/ai/components/mate.html). Connects through your application's existing [DoctrineBundle](https://github.com/doctrine/DoctrineBundle) DBAL configuration — no standalone MCP server or custom database config required.

## Capabilities

| Capability | Type | Description |
|---|---|---|
| `database-query` | Tool | Run validated read-only SQL queries for debugging and data inspection |
| `database-schema` | Tool | Inspect schema objects in summary, columns, or full detail |
| `db://{connection}` | Resource | Discovery summary for one connection: tables, views, routines, and metadata |

All capabilities use the host application's Doctrine DBAL connections. When `connection` is omitted, the default Doctrine connection is used automatically.

## Supported Databases

- **MySQL** 8.0+
- **PostgreSQL** 16+
- **SQLite** 3+

## Requirements

- PHP 8.2+
- Symfony 7.3+ or 8.0+
- Doctrine DBAL 4.0+
- DoctrineBundle 2.14+ (must be installed and configured in the host application)

## Installation

```bash
composer require --dev matesofmate/database-extension

# Discover the new capabilities
vendor/bin/mate discover
```

The extension detects DoctrineBundle automatically. If DoctrineBundle is not installed or configured, no database capabilities are exposed — the extension stays silent rather than offering broken tools.

## Read-Only Safety

Query execution is protected by two independent layers:

1. **Driver-level read-only mode** — A DBAL middleware sets the database session to read-only (`SET SESSION transaction_read_only = 1` on MySQL, `SET default_transaction_read_only = on` on PostgreSQL, `PRAGMA query_only = ON` on SQLite) before any query runs.
2. **Defensive query validation** — Before execution, every query is checked for:
   - Write keywords (`INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `CREATE`, `TRUNCATE`, etc.)
   - Transaction control (`COMMIT`, `ROLLBACK`, `TRANSACTION`)
   - Multiple statements (semicolon-separated)
   - Unbounded `SELECT` without `WHERE` or `LIMIT`

All queries execute inside a transaction that is always rolled back, regardless of outcome.

## Tool Reference

### `database-query`

Run a read-only SQL query against a Doctrine DBAL connection.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `query` | string | yes | — | SQL query to validate and execute |
| `connection` | string | no | default connection | Doctrine DBAL connection name |

Returns query result rows in TOON format. Long text values (>200 characters) are truncated to `<TEXT>` in multi-row results to reduce token usage.

### `database-schema`

Inspect database schema objects with configurable detail and filtering.

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `connection` | string | no | default connection | Doctrine DBAL connection name |
| `filter` | string | no | `""` | Object name filter |
| `detail` | string | no | `summary` | Detail level: `summary`, `columns`, `full` |
| `matchMode` | string | no | `contains` | Filter matching: `contains`, `prefix`, `exact`, `glob` |
| `includeViews` | bool | no | `false` | Include views in the response |
| `includeRoutines` | bool | no | `false` | Include procedures/functions/sequences/triggers |

### `db://{connection}`

Resource template returning a discovery summary for a single connection. Includes connection metadata (driver, platform, server version), table names, view names, and routine names.

## V1 Scope

This is the initial release. The following limitations apply:

- **Single summary resource** — One `db://{connection}` resource per connection. Per-table, per-view, or per-routine resources are not included.
- **No PII redaction** — Query results are returned as-is. Sensitive data filtering is not implemented.
- **Doctrine-only** — All database access goes through DoctrineBundle DBAL. There is no custom or standalone connection path.
- **MySQL, PostgreSQL, SQLite** — Other engines supported by Doctrine DBAL may work but are not tested.

## How It Works

```
┌──────────────┐      ┌────────────────────┐      ┌──────────────────┐
│  Mate AI     │─────▶│  DatabaseQueryTool  │─────▶│ SafeQueryExecutor│
│  Assistant   │      │  DatabaseSchemaTool │      │ DatabaseSchema   │
│              │      │  ConnectionResource │      │  Service         │
└──────────────┘      └────────────────────┘      └──────────────────┘
                              │                           │
                              ▼                           ▼
                      ┌────────────────┐          ┌──────────────────┐
                      │ Connection     │─────────▶│ ReadOnly         │
                      │  Resolver      │          │  Middleware       │
                      └────────────────┘          └──────────────────┘
                              │                           │
                              ▼                           ▼
                      ┌────────────────────────────────────────────┐
                      │     DoctrineBundle DBAL Connections         │
                      │     (MySQL · PostgreSQL · SQLite)           │
                      └────────────────────────────────────────────┘
```

The extension reads connection names and metadata from DoctrineBundle's service layer. Resolved connections are rebuilt through the read-only DBAL middleware before any query or schema operation executes.

## Contributing

### Running Tests

Tests run inside Docker containers against real MySQL, PostgreSQL, and SQLite databases. Docker and Docker Compose are the only host-level requirements.

```bash
# Run the default test suite (PHP 8.2 / Symfony ^7.3)
composer test

# Run the full matrix (PHP 8.2/Symfony ^7.3, PHP 8.3/Symfony ^7.3, PHP 8.4/Symfony ^8.0)
composer test:docker

# Run a single matrix entry
bash tests/bin/run-docker-phpunit.sh php82
bash tests/bin/run-docker-phpunit.sh php83
bash tests/bin/run-docker-phpunit.sh php84
```

The Docker Compose stack (`docker-compose.test.yaml`) starts MySQL 8.0, PostgreSQL 16, and an appropriately versioned PHP container. Database services start with health checks; tests begin only after the databases are ready.

### Code Quality

```bash
# Lint (runs in PHP 8.2 Docker container)
composer lint

# Auto-fix code style and refactorings
composer fix
```

Local-only variants that skip Docker are available for faster iteration:

```bash
composer lint:local    # Runs Rector, PHP CS Fixer, PHPStan on host PHP
composer fix:local     # Applies Rector and PHP CS Fixer fixes on host PHP
composer test:local    # Runs PHPUnit on host PHP (requires local database setup)
composer coverage:local
```

### Coverage

```bash
# Generate coverage reports (HTML + Clover) in coverage/
composer coverage
```

### CI

GitHub Actions runs on every push and pull request:

- **Lint** — `composer lint` (PHP 8.2 Docker container)
- **Test matrix** — PHP 8.2/Symfony ^7.3, PHP 8.3/Symfony ^7.3, PHP 8.4/Symfony ^8.0

See `.github/workflows/ci.yml`.

## Resources

- [Symfony AI Mate Docs](https://symfony.com/doc/current/ai/components/mate.html)
- [Creating MCP Extensions](https://symfony.com/doc/current/ai/components/mate/extensions.html)
- [MatesOfMate Contributing Guide](https://github.com/matesofmate/.github/blob/main/CONTRIBUTING.md)
- [Extension template documentation](TEMPLATE.md)

## License

MIT — see [LICENSE](LICENSE).
