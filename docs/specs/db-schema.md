# Feature Implementation Spec: Database schema for the domain model

## Source Feature

- `id`: db-schema
- `area`: data
- `depends_on`: bootstrap-project-skeleton (accepted 2026-11-17)
- `status`: not_started (at planning time)
- `source`: `feature_list.json`

## Goal

Add `db/schema.sql`, a single drop-and-create script that, run against an existing empty or previously-schema'd MariaDB database, creates every table of `docs/domain-model.md`: users, auth tokens, work orders, status history, L1/L2/L3 typologies (L3 with default priority), locations, location types and the AI Prefill log. Foreign keys enforce L3→L2→L1 and work order→location/L3 typology/creator/assignee; CHECK constraints keep status and priority to the Spanish values in `CONTEXT.md`. Plain PHP tests prove the script runs twice and the constraints hold, without ever touching a database other than this worktree's.

## Non-Goals

- No seed rows of any kind, not even location types or a service account (`db-seed-data`).
- No PDO connection class in `src/`, no repositories, no API code (`api-*` features). The DB helper lives in `tests/lib/` only.
- No lifecycle enforcement in SQL (allowed transitions, "En resolución requires assignee", "service accounts are never creator/assignee"): these are API rules (`api-create-work-order`, `api-update-work-order`). No triggers, stored procedures, views or events.
- No migrations framework; the PoC re-runs the script (data loss is accepted, seed reloads).
- No `CREATE DATABASE` / `USE` / `DROP DATABASE` inside `db/schema.sql`.
- No change to the MariaDB service, its users or grants, and no access to `hotel_maintenance` or any other `hm_*` database.

## Job Story

When I (developer or agent) start a feature that needs data,
I want one SQL script that builds the whole schema in my own database,
so I can seed it and build endpoints on tables whose integrity the database already enforces.

## Users And Permissions

- Developer/agent: connects as `hm_app@localhost` (ALL on `hotel_maintenance` and `hm\_%` only, see `ARCHITECTURE.md`). In this worktree the database is `hm_db_schema` (`db.name` in the git-ignored `config/config.php`). No end-user roles involved.

## Acceptance Scenarios

### Scenario 1: Script runs twice without errors
Given the database `hm_db_schema` exists (utf8mb4)
When `db/schema.sql` is run against it with the mysql client, and run again
Then both runs exit 0 and `SHOW TABLES` lists exactly the 10 tables below.

### Scenario 2: Typology tree is enforced
Given a fresh schema
When a level 2 typology references a non-existent level 1 id, or a level 3 references a non-existent level 2 id, or a level 1 with children is deleted
Then MariaDB rejects the statement (error 1452 / 1451).

### Scenario 3: Work order references are enforced
Given one valid user, location (with type) and L3 typology
When a work order is inserted with an unknown location, L3 typology, creator or assignee id
Then each insert fails with error 1452; with valid ids and `assignee_user_id` NULL it succeeds.

### Scenario 4: Spanish domain values are enforced and round-trip
Given a fresh schema and the server's default `sql_mode` (non-strict on this machine)
When a work order or history entry uses status `En resolución` and priority `Alta`
Then the values read back byte-identical; when status `Abierto` or priority `Urgente` (or L3 default priority `Urgente`) is used, the insert fails (CHECK constraint, error 4025).

### Scenario 5: Gate stays safe and non-blocking
Given `config/config.php` is missing, or `db.name` does not start with `hm_`
When `./init.sh` runs
Then the live DB tests are skipped with one `SKIP tests/db/...` line, other tests still run, and no database is touched. With `db.name = hm_db_schema` the DB tests run and pass.

## Repository Research

### Files Inspected

- `feature_list.json`, `PROGRESS.md` — db-schema is the next ready feature; dependency accepted.
- `docs/domain-model.md`, `CONTEXT.md` — entities, attributes, status/priority values, relationships.
- `docs/technical-discovery.md` — token stored server-side with expiry; AI Prefill log contents (timestamp, user, provider/model, description, tool calls, raw output, validated result, latency, token usage); prefill notes go to the log.
- `ARCHITECTURE.md`, `CONSTRAINTS.md`, `AGENTS.md` — `db/` reserved for `schema.sql`/`seed.sql`; `hm_app` account; `mysql.exe --defaults-extra-file=config/mysql.cnf` (first option); tests are plain PHP.
- `config/config.example.php`, `config/config.php` (non-secret keys only), `config/mysql.cnf(.example)` — `db.name` is `hm_db_schema` here; `mysql.cnf` has no `database=` line, so the DB name must be passed on the command line.
- `tests/run.php`, `tests/lib/assert.php`, `tests/bootstrap/config_test.php` — runner auto-requires `tests/lib/*.php`; a file may return `[]`; no built-in skip.
- `init.sh` — last manual follow-up line says "database: commands arrive with feature 'db-schema'".
- Read-only server probe: MariaDB 10.4.27, InnoDB default, server charset utf8mb4, `sql_mode = NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION` (not strict), `hm_db_schema` does not exist yet, PHP 8.2.0 with `pdo_mysql`.

### Existing Patterns To Follow

- Tests: `tests/<area>/*_test.php` returning `array<string, callable>`, using `assert_*` helpers, no `assert()`.
- Config via `App\Config::loadDefault()`; never print or log `db.password`.
- English identifiers in code; Spanish stored domain values.

### Current Gaps

- No `db/` directory, no DB helper, no DB tests, database `hm_db_schema` not created.
- Non-strict `sql_mode`: ENUMs would silently store `''`; hence VARCHAR + CHECK (enforced in MariaDB ≥10.2 regardless of mode).

## Technical Approach

`db/schema.sql` (UTF-8 without BOM, LF): header comment with the apply command; `SET NAMES utf8mb4;` `SET FOREIGN_KEY_CHECKS = 0;` then `DROP TABLE IF EXISTS` for all 10 tables, `SET FOREIGN_KEY_CHECKS = 1;`, then `CREATE TABLE` in dependency order. Every table: `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`, `id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY`. Script format rule (so PHP can split it): one statement per `;` at end of line, only full-line `-- ` comments, no `;` inside comments or literals, no `DELIMITER`.

| Table | Columns (besides `id`) and constraints |
| --- | --- |
| `location_types` | `name VARCHAR(50) NOT NULL UNIQUE` |
| `locations` | `name VARCHAR(100) NOT NULL UNIQUE`, `location_type_id` NOT NULL FK → `location_types` |
| `level1_typologies` | `name VARCHAR(100) NOT NULL UNIQUE` |
| `level2_typologies` | `level1_typology_id` NOT NULL FK → `level1_typologies`, `name VARCHAR(100) NOT NULL`, UNIQUE (`level1_typology_id`, `name`) |
| `level3_typologies` | `level2_typology_id` NOT NULL FK → `level2_typologies`, `name VARCHAR(100) NOT NULL`, `default_priority VARCHAR(10) NOT NULL` CHECK IN (`Baja`,`Media`,`Alta`), UNIQUE (`level2_typology_id`, `name`) |
| `users` | `username VARCHAR(50) NOT NULL UNIQUE`, `password_hash VARCHAR(255) NOT NULL`, `first_name VARCHAR(100) NOT NULL`, `last_names VARCHAR(150) NOT NULL DEFAULT ''`, `is_service_account TINYINT(1) NOT NULL DEFAULT 0` CHECK IN (0,1) |
| `auth_tokens` | `user_id` NOT NULL FK → `users` ON DELETE CASCADE, `token_hash CHAR(64) NOT NULL UNIQUE` (hex SHA-256 of the token; raw token never stored), `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `expires_at DATETIME NOT NULL`, INDEX (`expires_at`) |
| `work_orders` | `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `creator_user_id` NOT NULL FK → `users`, `assignee_user_id` NULL FK → `users`, `status VARCHAR(20) NOT NULL` CHECK IN (`Pendiente`,`Asignado`,`En resolución`,`Resuelto`,`Cancelado`), `location_id` NOT NULL FK → `locations`, `level3_typology_id` NOT NULL FK → `level3_typologies`, `priority VARCHAR(10) NOT NULL` CHECK IN (Baja/Media/Alta), `description TEXT NOT NULL`, INDEX (`created_at`), INDEX (`status`) |
| `work_order_status_history` | `work_order_id` NOT NULL FK → `work_orders` ON DELETE CASCADE, `status VARCHAR(20) NOT NULL` same CHECK, `changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `changed_by_user_id` NOT NULL FK → `users`, INDEX (`work_order_id`, `changed_at`, `id`) |
| `ai_prefill_log` | `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `user_id` NOT NULL FK → `users`, `provider VARCHAR(50) NOT NULL`, `model VARCHAR(100) NOT NULL`, `description TEXT NOT NULL`, `tool_calls LONGTEXT NULL`, `raw_output LONGTEXT NULL`, `validated_result LONGTEXT NULL`, `prefill_notes LONGTEXT NULL`, `error TEXT NULL`, `latency_ms INT UNSIGNED NULL`, `input_tokens INT UNSIGNED NULL`, `output_tokens INT UNSIGNED NULL` |

Decisions: L1 and L2 are not stored on `work_orders` (derived from L3, so they can never be inconsistent). Every FK not marked CASCADE is `ON DELETE RESTRICT ON UPDATE RESTRICT`; give FKs explicit names `fk_<table>_<column>`. `tool_calls`, `validated_result`, `prefill_notes` hold JSON text with `CHECK (col IS NULL OR JSON_VALID(col))`; `raw_output` is free text (the model may return non-JSON). Status history order is `changed_at, id`. No `revoked_at`: `api-logout` deletes the token row.

Test support, `tests/lib/db.php` (auto-loaded by the runner): `test_db_name_is_disposable(string $name): bool` (pure: matches `/^hm_[a-z0-9_]+$/`), `test_db_skip_reason(): ?string` (null when `config/config.php` exists and `db.name` is disposable, otherwise a reason), `test_db_connect(): PDO` (DSN with `dbname` from config, `charset=utf8mb4`, `ERRMODE_EXCEPTION`, no session `sql_mode` change, so CHECKs are proven under the server default), `test_db_split_sql(string $sql): list<string>`, `test_db_apply_sql_file(PDO, string $path): void` (executes each statement via `PDO::exec`). Helpers never issue `CREATE/DROP DATABASE` or `USE`, and never print the password.

`tests/db/db_helper_test.php` (always runs, no connection): `test_db_name_is_disposable` accepts `hm_db_schema`, rejects `hotel_maintenance`, `HM_x`, `hm_` and `hm_x;drop`; `test_db_split_sql` splits a small sample with comments into the right statements; `db/schema.sql` contains no `CREATE DATABASE`, `DROP DATABASE`, `USE ` or `DELIMITER`.

`tests/db/schema_test.php`: if `test_db_skip_reason()` is non-null, `echo "SKIP tests/db/schema_test.php: <reason>\n"` and return `[]`. Otherwise tests: (1) applying the file twice succeeds and the table list equals the 10 names; (2) `information_schema.REFERENTIAL_CONSTRAINTS`/`KEY_COLUMN_USAGE` (filtered by `DATABASE()`) contain each expected FK column→table pair; (3) Scenario 2 inserts/deletes fail with `PDOException` and MySQL code 1452/1451 (`$e->errorInfo[1]`); (4) Scenario 3; (5) Scenario 4 incl. `En resolución` round-trip and invalid values (code 4025); (6) JSON CHECK rejects `'{bad'` in `ai_prefill_log.tool_calls`; (7) `users` defaults (`is_service_account` 0) and duplicate `username` rejected (1062). Each test starts by re-applying `db/schema.sql`; the last test leaves an empty schema.

## Expected File Changes

- `db/schema.sql` — create; the schema above.
- `tests/lib/db.php` — create; guarded connection, SQL splitter, applier.
- `tests/db/db_helper_test.php` — create; connection-free guard, splitter and script-content tests.
- `tests/db/schema_test.php` — create; Scenarios 1–5 as tests.
- `init.sh` — modify only the database follow-up line (create-database + apply commands, see Verification). Keep LF and 100755.
- `ARCHITECTURE.md`, `CONSTRAINTS.md`, `AGENTS.md` — update (below).
- `feature_list.json` — only the `db-schema` entry (status, evidence, notes). `PROGRESS.md` — append only a `db-schema` section.

## Visual Design Impact

- UI involved: no. Design source: not applicable. New design artifact: no.

## Durable Documentation Impact

- `ARCHITECTURE.md`: update — `db/schema.sql` in the layout (no longer "reserved"), a Database section: table list, naming (`snake_case`, plural, `<entity>_id` FKs, `fk_` names), utf8mb4_unicode_ci, Spanish values via CHECK, drop-and-create semantics, apply command, `tests/lib/db.php` and the `hm_` test guard.
- `CONSTRAINTS.md`: update — add: (a) **MUST NOT** name a database in `db/*.sql`; scripts run against the database given on the command line. (b) **MUST** run destructive DB tests only against databases named `hm_*`; the test helper skips otherwise. (c) **MUST** keep `db/*.sql` splittable (statement ends with `;` at line end, full-line `--` comments, no `DELIMITER`). (d) **MUST** store status/priority as the exact Spanish strings, enforced by CHECK (server `sql_mode` is not strict).
- `AGENTS.md`: update — one line in Startup Workflow: create the worktree database once and apply `db/schema.sql` (commands as in Verification).
- `docs/technical-discovery.md`: update one sentence in Data and Storage pointing to `db/schema.sql` and the token-hash decision.
- `docs/domain-model.md`, `CONTEXT.md`: not needed — no domain change.

## Implementation Plan

1. Create `hm_db_schema` once (manual command below); confirm with `SHOW DATABASES LIKE 'hm_db_schema'`.
2. Write `db/schema.sql`; apply it twice with the mysql client.
3. Write `tests/lib/db.php` and `tests/db/schema_test.php`; run `php tests/run.php db`.
4. Prove the guard: run once with `config/config.php` renamed (then restored) and observe the SKIP line; the name rule is unit-tested without any connection.
5. Update `init.sh` follow-up line and the docs; run `./init.sh`; record evidence.

## Implementation Tasks

- [ ] Create `db/schema.sql` per the table above (drop block, then 10 CREATEs in FK order).
- [ ] Create `tests/lib/db.php` with the five helpers and the `hm_` guard.
- [ ] Create `tests/db/db_helper_test.php` (no DB connection).
- [ ] Create `tests/db/schema_test.php` covering Scenarios 1–5 and the JSON/unique checks.
- [ ] Update `init.sh` database follow-up line.
- [ ] Update `ARCHITECTURE.md`, `CONSTRAINTS.md`, `AGENTS.md`, `docs/technical-discovery.md`.
- [ ] Run verification; record evidence in `feature_list.json` (db-schema only) and a `db-schema` section in `PROGRESS.md`.

## Verification Plan

All commands from the repo root in Git Bash; `--defaults-extra-file` must be the first option; always name `hm_db_schema` explicitly and nothing else.

- Create once: `/c/xampp/mysql/bin/mysql.exe --defaults-extra-file=config/mysql.cnf -e "CREATE DATABASE IF NOT EXISTS hm_db_schema CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"`.
- Apply twice: `/c/xampp/mysql/bin/mysql.exe --defaults-extra-file=config/mysql.cnf hm_db_schema < db/schema.sql; echo "exit $?"` → `exit 0` both times.
- Inspect: `... hm_db_schema -e "SHOW TABLES; SHOW CREATE TABLE work_orders\G"` → 10 tables; FKs and CHECKs visible.
- Manual FK probe: `... hm_db_schema -e "INSERT INTO level2_typologies (level1_typology_id, name) VALUES (999, 'x')"` → `ERROR 1452`.
- `php tests/run.php db` → all `tests/db/schema_test.php` tests PASS, exit 0.
- Guard: with `config/config.php` temporarily renamed (restore it immediately afterwards), `php tests/run.php` prints `SKIP tests/db/schema_test.php: ...` and still exits 0. The non-`hm_` branch is covered by a pure unit test of the guard (see below), never by pointing config at another database.
- Isolation: no run may touch `hotel_maintenance` or any other `hm_*` database.
- `./init.sh` → exit 0, syntax check count +3 files (16), test count increased, no long-running process.
- No dev server needed (no HTTP surface). If one is started for any reason, use `php -S 127.0.0.1:8101 -t public` and stop it.
- E2E: not applicable (no user-visible or API flow).
- Secrets: `git diff` and new files contain no password; `git status` shows no `config/config.php` or `config/mysql.cnf`.

## Evidence To Capture

- Both mysql apply runs with exit codes; `SHOW TABLES` output.
- `php tests/run.php db` PASS lines and summary; SKIP line from the guard run.
- `./init.sh` summary lines (files, tests, exit code).
- MariaDB version used (10.4.27).

## Validator Checklist

- [ ] Only the files listed above changed; no seed rows, no `src/` DB code, no triggers/procedures.
- [ ] `db/schema.sql` names no database and runs twice against `hm_db_schema` with exit 0.
- [ ] All 10 tables exist with the columns, FKs (RESTRICT except the two CASCADEs), uniques and CHECKs in the table above.
- [ ] Tests prove 1452/1451 for typology and work-order FKs, 4025 for bad status/priority/JSON, `En resolución` round-trips.
- [ ] Guard skips cleanly without config or with a non-`hm_` name; no other database was touched.
- [ ] `./init.sh` exits 0; `init.sh` still LF and 100755.
- [ ] Docs updated as listed; `feature_list.json` changes limited to `db-schema`; `PROGRESS.md` has an appended `db-schema` section; no commit or push.

## Implementation Notes

- **CHECK collation (spec gap fixed).** With the table collation `utf8mb4_unicode_ci`, a plain `CHECK (status IN (...))` is case- and accent-insensitive: the first test run accepted `'En resolucion'` (and would accept `'pendiente'`, `'alta'`). Every status/priority CHECK therefore compares `col COLLATE utf8mb4_bin IN (...)`. Columns keep `utf8mb4_unicode_ci`. The tests assert 4025 for `En resolucion`, `pendiente`, `alta` and `''`.
- **Byte comparison (validator revise, 2026-11-27).** `utf8mb4_bin` is PAD SPACE on MariaDB 10.4, so `'Pendiente '` passed the CHECKs. Every status/priority CHECK now uses `CAST(col AS BINARY) IN (...)` (stored as `cast(col as char charset binary) in (...)`). `En resolución` still round-trips byte-identical (`...C3B36E`) both when the schema and data are applied via the mysql client and via the PHP test runner. The tests also assert 4025 for `'Pendiente '`, `'En resolución '` and `'Alta '`.
- **Missing database fails, not skips.** With a valid `hm_*` `db.name` and no such database, the live DB tests fail with `Unknown database`. `AGENTS.md` now puts the database step before `./init.sh`, and `init.sh` prints the creation hint before the tests too.
- **Guard proof without renaming the real config.** Renaming `config/config.php` was denied by the session's permissions. Instead the real `tests/db/schema_test.php` was loaded in-process with `APP_ROOT` pre-defined (the bootstrap honors it) to a root without `config/` (→ `SKIP ...: config/config.php is missing`, 0 tests) and to a temporary fixture with `db.name = hotel_maintenance` (→ `SKIP ...: db.name is not a disposable hm_* database`, 0 tests, no connection attempted). The fixture was removed afterwards. The runner accepts a file returning `[]`.
- Constraint, unique and index names follow `fk_`/`uq_`/`chk_`/`ix_<table>_...`. Additional test coverage beyond the spec: engine/collation of every table, delete/update rules per FK, L2 duplicate name (1062), deleting a referenced location/creator (1451), history and token cascades, `is_service_account = 2` (4025), invalid JSON in each JSON column.
