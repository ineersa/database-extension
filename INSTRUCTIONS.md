## Database Extension — Workflow Guide

### Discovery Flow

1. If the client supports resources, read `db://{connection}` to get tables, views, and routines for a connection.
2. Use `database-schema` to inspect column types and structure before writing queries.
3. Use `database-query` for targeted read-only SELECT queries.

Always inspect schema before querying — this avoids "table not found" and wrong-column errors.

### When to Use Each Tool

| Goal                                | Tool              | Example                                                   |
| ----------------------------------- | ----------------- | --------------------------------------------------------- |
| List all tables                     | `database-schema` | `detail="summary"`                                        |
| See column types for a table        | `database-schema` | `filter="users", detail="columns"`                        |
| Get full structure with indexes/FKs | `database-schema` | `filter="orders", detail="full"`                          |
| Get trigger/function/procedure body | `database-schema` | `filter="trg_name", detail="full", includeRoutines=true`  |
| Get view SQL definition             | `database-schema` | `filter="active_users", detail="full", includeViews=true` |
| Find tables matching a prefix       | `database-schema` | `filter="app_", matchMode="prefix"`                       |
| Run a data query                    | `database-query`  | `query="SELECT id, name FROM users LIMIT 10"`             |
| Count rows                          | `database-query`  | `query="SELECT COUNT(*) FROM orders"`                     |
| Inspect a specific row fully        | `database-query`  | `query="SELECT * FROM users WHERE id = 42"`               |

### Error Handling

- Errors include `error` (what went wrong) and `hint` (what to try next).
- Connection errors hint at available connection names.
- Query or schema failures against a working connection usually mean wrong table/column names — re-check with `database-schema`.
