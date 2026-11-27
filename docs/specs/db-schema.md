# Feature Implementation Spec: Database schema for the domain model

## Source Feature

- `id`: db-schema
- `area`: data
- `depends_on`: bootstrap-project-skeleton (accepted 2026-11-17)
- `status`: not_started (at original planning); accepted 2026-11-27 (commit d1e7bbb, unpublished); **reopened `in_progress` 2026-11-27 by the Change Request below**
- `source`: `feature_list.json`

## Change Request (2026-11-27)

The user filed a change request after acceptance. The decisions below are final. **This section is authoritative: where any older text in this spec disagrees, this section wins.** Superseded text elsewhere is marked "Superseded (CR 2026-11-27)".

User decisions:

1. Status and priority values move to lookup tables referenced by foreign keys. The `VARCHAR` columns `work_orders.status`, `work_orders.priority`, `work_order_status_history.status`, `level3_typologies.default_priority` and their `CHECK (CAST(... AS BINARY) IN (...))` constraints are removed.
2. New table `statuses`: `id`, unique `name` (exact Spanish values of `CONTEXT.md`), `is_closed` (true only for Resuelto and Cancelado), `sort_order` (Pendiente=1 ... Cancelado=5).
3. New table `priorities`: `id`, unique `name` (Baja, Media, Alta), `sort_order` (Baja=1, Media=2, Alta=3).
4. Integer FKs: `work_orders.status_id` → `statuses`, `work_order_status_history.status_id` → `statuses`, `work_orders.priority_id` → `priorities`, `level3_typologies.default_priority_id` → `priorities`.
5. These are fixed domain values: `db/schema.sql` itself inserts the `statuses` and `priorities` rows with explicit ids, so the FKs work right after applying the schema. `db-seed-data` stays responsible for hotel sample data only.
6. Status transition rules stay in PHP application code, not in the database.

### CR design (planner decisions within the user decisions)

New tables (both `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`):

| Table | Columns and constraints |
| --- | --- |
| `statuses` | `id INT UNSIGNED NOT NULL PRIMARY KEY` (no AUTO_INCREMENT: ids are fixed), `name VARCHAR(20) NOT NULL`, `is_closed TINYINT(1) NOT NULL`, `sort_order INT UNSIGNED NOT NULL`, `uq_statuses_name UNIQUE (name)`, `uq_statuses_sort_order UNIQUE (sort_order)`, `chk_statuses_is_closed CHECK (is_closed IN (0, 1))` |
| `priorities` | `id INT UNSIGNED NOT NULL PRIMARY KEY` (no AUTO_INCREMENT), `name VARCHAR(10) NOT NULL`, `sort_order INT UNSIGNED NOT NULL`, `uq_priorities_name UNIQUE (name)`, `uq_priorities_sort_order UNIQUE (sort_order)` |

Fixed rows (ids equal `sort_order`; these ids are a contract that the seed and the API will rely on):

| `statuses` id | name | is_closed | sort_order | UTF-8 hex of name |
| --- | --- | --- | --- | --- |
| 1 | Pendiente | 0 | 1 | `50656E6469656E7465` |
| 2 | Asignado | 0 | 2 | `417369676E61646F` |
| 3 | En resolución | 0 | 3 | `456E207265736F6C756369C3B36E` |
| 4 | Resuelto | 1 | 4 | `52657375656C746F` |
| 5 | Cancelado | 1 | 5 | `43616E63656C61646F` |

| `priorities` id | name | sort_order | UTF-8 hex of name |
| --- | --- | --- | --- |
| 1 | Baja | 1 | `42616A61` |
| 2 | Media | 2 | `4D65646961` |
| 3 | Alta | 3 | `416C7461` |

Column replacements (same position as the column they replace; type `INT UNSIGNED NOT NULL` to match the parent `id`):

| Old (removed) | New | FK name | Rules |
| --- | --- | --- | --- |
| `level3_typologies.default_priority` + `chk_level3_typologies_default_priority` | `default_priority_id` | `fk_level3_typologies_default_priority_id` → `priorities (id)` | `ON DELETE RESTRICT ON UPDATE RESTRICT` |
| `work_orders.status` + `chk_work_orders_status` + `ix_work_orders_status` | `status_id` | `fk_work_orders_status_id` → `statuses (id)` | RESTRICT / RESTRICT |
| `work_orders.priority` + `chk_work_orders_priority` | `priority_id` | `fk_work_orders_priority_id` → `priorities (id)` | RESTRICT / RESTRICT |
| `work_order_status_history.status` + `chk_work_order_status_history_status` | `status_id` | `fk_work_order_status_history_status_id` → `statuses (id)` | RESTRICT / RESTRICT |

No explicit index on the new FK columns: InnoDB creates one per FK, which also serves status/priority filters (the old `ix_work_orders_status` goes away).

**Name collation (decision).** `statuses.name` and `priorities.name` keep the table collation `utf8mb4_unicode_ci` (state it in the schema comment; no `COLLATE` clause needed). Reason, verified by a read-only probe on this server (MariaDB 10.4.27): under `utf8mb4_unicode_ci`, `'pendiente' = 'Pendiente'`, `'En resolucion' = 'En resolución'` and `'Pendiente ' = 'Pendiente'` are all true, so the UNIQUE index rejects case, accent and trailing-space look-alikes of a canonical name (error 1062). Under `utf8mb4_bin`, `'pendiente'` and `'En resolucion'` would be distinct, so a look-alike row could coexist with the canonical one; a binary collation therefore gives weaker uniqueness. Byte-exactness of the stored names comes from the fixed `INSERT` in `db/schema.sql` (the only writer of these rows) and is proven by HEX assertions. Application code refers to statuses and priorities by id; a name lookup, if ever used, resolves to the canonical row. Known gap (accepted): a TAB is not ignorable in this collation, so `'Pendiente\t'` would be a distinct name; harmless because nothing but `db/schema.sql` writes these tables, and the tests pin the exact row set.

**Byte-exact `En resolución` via the mysql client.** `SET NAMES utf8mb4;` stays the first statement of `db/schema.sql`, before any `INSERT`; the file stays UTF-8 without BOM. Applying the file with `mysql.exe` must store `456E207265736F6C756369C3B36E` for status id 3 (manual check in Verification), and the PHP tests assert the same hex after applying through PDO.

**Drop order.** Keep the `SET FOREIGN_KEY_CHECKS = 0;` ... `= 1;` wrapper, and drop in reverse dependency order so the order is correct even without it: `ai_prefill_log`, `work_order_status_history`, `work_orders`, `auth_tokens`, `users`, `level3_typologies`, `level2_typologies`, `level1_typologies`, `locations`, `location_types`, `priorities`, `statuses`. A database still holding the pre-CR schema (no lookup tables) is handled by `DROP TABLE IF EXISTS`.

**Create order.** `statuses`, then its `INSERT`; `priorities`, then its `INSERT`; then the existing order (`location_types`, `locations`, `level1_typologies`, `level2_typologies`, `level3_typologies`, `users`, `auth_tokens`, `work_orders`, `work_order_status_history`, `ai_prefill_log`). Each lookup `INSERT` is one multi-row statement listing columns explicitly (`INSERT INTO statuses (id, name, is_closed, sort_order) VALUES` / one row per line / `;` only at the end of the last line), which keeps the file splittable.

**Unchanged:** every other table, column, FK, UNIQUE, JSON CHECK, `chk_users_is_service_account`, the no-database-name rule, the splittable format, the `hm_*`-only test guard, drop-and-create semantics, `tests/lib/db.php`, `init.sh`.

## Goal

Add `db/schema.sql`, a single drop-and-create script that, run against an existing empty or previously-schema'd MariaDB database, creates every table of `docs/domain-model.md`: users, auth tokens, work orders, status history, L1/L2/L3 typologies (L3 with default priority), locations, location types and the AI Prefill log, **plus (CR 2026-11-27) the lookup tables `statuses` and `priorities` with their fixed rows: 12 tables in total**. Foreign keys enforce L3→L2→L1 and work order→location/L3 typology/creator/assignee, **and (CR) work order / history status → `statuses`, work order priority and L3 default priority → `priorities`**. Superseded (CR 2026-11-27): "CHECK constraints keep status and priority to the Spanish values in `CONTEXT.md`". Plain PHP tests prove the script runs twice and the constraints hold, without ever touching a database other than this worktree's.

## Non-Goals

- No seed rows of any kind, not even location types or a service account (`db-seed-data`). **Exception (CR 2026-11-27):** the fixed `statuses` and `priorities` rows are part of the schema.
- No PDO connection class in `src/`, no repositories, no API code (`api-*` features), no PHP constants for status/priority ids (they belong to the first API feature that needs them). The DB helper lives in `tests/lib/` only.
- No lifecycle enforcement in SQL (allowed transitions, "En resolución requires assignee", "service accounts are never creator/assignee", "closed orders cannot be edited"; `is_closed` is data, not a rule): these are API rules in PHP (`api-create-work-order`, `api-update-work-order`). No triggers, stored procedures, views or events.
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

### Scenario 1: Script runs twice without errors (amended by CR)
Given the database `hm_db_schema` exists (utf8mb4)
When `db/schema.sql` is run against it with the mysql client, and run again
Then both runs exit 0, `SHOW TABLES` lists exactly the 12 tables (10 original + `statuses`, `priorities`), `statuses` holds exactly the 5 and `priorities` exactly the 3 fixed rows of the CR tables, and every other table is empty.

### Scenario 2: Typology tree is enforced
Given a fresh schema
When a level 2 typology references a non-existent level 1 id, or a level 3 references a non-existent level 2 id, or a level 1 with children is deleted
Then MariaDB rejects the statement (error 1452 / 1451).

### Scenario 3: Work order references are enforced
Given one valid user, location (with type) and L3 typology
When a work order is inserted with an unknown location, L3 typology, creator or assignee id
Then each insert fails with error 1452; with valid ids and `assignee_user_id` NULL it succeeds.

### Scenario 4: Spanish domain values are enforced and round-trip
Superseded (CR 2026-11-27) by Scenarios 4a and 4b. (Was: VARCHAR values checked by CHECK, error 4025.)

### Scenario 4a: Lookup rows are fixed, byte-exact and unique (CR)
Given a fresh schema (applied via PDO in tests, via the mysql client in manual verification)
When `statuses` and `priorities` are read
Then ids, names, `is_closed` and `sort_order` equal the CR tables exactly and `HEX(name)` equals the listed hex (`En resolución` = `456E207265736F6C756369C3B36E`); inserting a look-alike name (`pendiente`, `PENDIENTE`, `En resolucion`, `En resolución `, `Pendiente `, `alta`, `Alta `, `MEDIA`) with a new id and sort order fails with 1062, as do a duplicate id and a duplicate `sort_order`; `is_closed = 2` fails with 4025.

### Scenario 4b: Status and priority FKs are enforced (CR)
Given a fresh schema, the server default `sql_mode` (non-strict) and one valid location, L3 typology and user
When a work order uses `status_id` 3 and `priority_id` 3
Then it saves and a join reads back `En resolución` / `Alta`; every status id 1–5 and priority id 1–3 is accepted. Unknown ids (0, 6 or 4, 99) in `work_orders.status_id`, `work_orders.priority_id`, `work_order_status_history.status_id` and `level3_typologies.default_priority_id` fail with 1452 (insert and update); explicit NULL fails with 1048; deleting or changing the id of a referenced `statuses` / `priorities` row fails with 1451.

### Scenario 5: Gate stays safe and non-blocking
Given `config/config.php` is missing, or `db.name` does not start with `hm_`
When `./init.sh` runs
Then the live DB tests are skipped with one `SKIP tests/db/...` line, other tests still run, and no database is touched. With `db.name = hm_db_schema` the DB tests run and pass.

## Repository Research

### Files Inspected

- `feature_list.json`, `PROGRESS.md` — db-schema accepted then reopened `in_progress` by the CR (working-tree change to `feature_list.json`).
- `docs/domain-model.md`, `CONTEXT.md` — entities, attributes, status/priority values, closed statuses (Resuelto, Cancelado), relationships.
- `docs/technical-discovery.md` — token stored server-side with expiry; AI Prefill log contents; Data and Storage sentence pointing to `db/schema.sql`.
- `ARCHITECTURE.md` (Database section), `CONSTRAINTS.md` (status/priority CHECK rule), `AGENTS.md` (startup step 5) — all describe the pre-CR design where noted below.
- CR re-read: `db/schema.sql` (10 tables, CAST BINARY CHECKs, header comment), `tests/lib/db.php` (no change needed), `tests/db/db_helper_test.php` (forbids `INSERT`, expects 10 CREATEs), `tests/db/schema_test.php` (7 live tests built on VARCHAR status/priority).
- `config/config.example.php`, `config/config.php` (non-secret keys only), `config/mysql.cnf(.example)` — `db.name` is `hm_db_schema` here; `mysql.cnf` has no `database=` line, so the DB name must be passed on the command line.
- `tests/run.php`, `tests/lib/assert.php` — runner auto-requires `tests/lib/*.php`; a file may return `[]`.
- Read-only server probes: MariaDB 10.4.27, InnoDB, utf8mb4, `sql_mode = NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION` (not strict); CR probe of collation equality (see CR "Name collation").

### Existing Patterns To Follow

- Tests: `tests/<area>/*_test.php` returning `array<string, callable>`, using `assert_*` helpers, no `assert()`.
- Config via `App\Config::loadDefault()`; never print or log `db.password`.
- English identifiers in code; Spanish stored domain values. Constraint names `fk_`/`uq_`/`chk_`/`ix_<table>_...`.

### Current Gaps

- (CR) `db/schema.sql` and both DB test files implement the superseded VARCHAR + CHECK design.
- Non-strict `sql_mode`: ENUMs would silently store `''`. Pre-CR this justified VARCHAR + CHECK; post-CR, FKs and NOT NULL are enforced regardless of mode (single-row NULL insert is error 1048 even when not strict).

## Technical Approach

`db/schema.sql` (UTF-8 without BOM, LF): header comment with the apply command; `SET NAMES utf8mb4;` `SET FOREIGN_KEY_CHECKS = 0;` then `DROP TABLE IF EXISTS` for all 12 tables in the CR drop order, `SET FOREIGN_KEY_CHECKS = 1;`, then `CREATE TABLE` (and the two lookup `INSERT`s) in the CR create order. Every table: `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`, `id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY` (except `statuses` and `priorities`: fixed ids, no AUTO_INCREMENT). Script format rule (so PHP can split it): one statement per `;` at end of line, only full-line `-- ` comments, no `;` inside comments or literals, no `DELIMITER`. Rewrite the header comment: drop the CHECK/CAST explanation; explain the lookup tables, fixed ids, the collation decision and that `db/schema.sql` is the only writer of those rows.

| Table | Columns (besides `id`) and constraints |
| --- | --- |
| `statuses`, `priorities` | (CR) see Change Request |
| `location_types` | `name VARCHAR(50) NOT NULL UNIQUE` |
| `locations` | `name VARCHAR(100) NOT NULL UNIQUE`, `location_type_id` NOT NULL FK → `location_types` |
| `level1_typologies` | `name VARCHAR(100) NOT NULL UNIQUE` |
| `level2_typologies` | `level1_typology_id` NOT NULL FK → `level1_typologies`, `name VARCHAR(100) NOT NULL`, UNIQUE (`level1_typology_id`, `name`) |
| `level3_typologies` | `level2_typology_id` NOT NULL FK → `level2_typologies`, `name VARCHAR(100) NOT NULL`, `default_priority_id INT UNSIGNED NOT NULL` FK → `priorities` (CR; superseded: `default_priority VARCHAR(10)` + CHECK), UNIQUE (`level2_typology_id`, `name`) |
| `users` | `username VARCHAR(50) NOT NULL UNIQUE`, `password_hash VARCHAR(255) NOT NULL`, `first_name VARCHAR(100) NOT NULL`, `last_names VARCHAR(150) NOT NULL DEFAULT ''`, `is_service_account TINYINT(1) NOT NULL DEFAULT 0` CHECK IN (0,1) |
| `auth_tokens` | `user_id` NOT NULL FK → `users` ON DELETE CASCADE, `token_hash CHAR(64) NOT NULL UNIQUE` (hex SHA-256 of the token; raw token never stored), `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `expires_at DATETIME NOT NULL`, INDEX (`expires_at`) |
| `work_orders` | `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `creator_user_id` NOT NULL FK → `users`, `assignee_user_id` NULL FK → `users`, `status_id INT UNSIGNED NOT NULL` FK → `statuses` (CR), `location_id` NOT NULL FK → `locations`, `level3_typology_id` NOT NULL FK → `level3_typologies`, `priority_id INT UNSIGNED NOT NULL` FK → `priorities` (CR), `description TEXT NOT NULL`, INDEX (`created_at`). Superseded (CR): `status VARCHAR(20)` / `priority VARCHAR(10)` with CHECKs and INDEX (`status`) |
| `work_order_status_history` | `work_order_id` NOT NULL FK → `work_orders` ON DELETE CASCADE, `status_id INT UNSIGNED NOT NULL` FK → `statuses` (CR; superseded: `status VARCHAR(20)` + CHECK), `changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `changed_by_user_id` NOT NULL FK → `users`, INDEX (`work_order_id`, `changed_at`, `id`) |
| `ai_prefill_log` | `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `user_id` NOT NULL FK → `users`, `provider VARCHAR(50) NOT NULL`, `model VARCHAR(100) NOT NULL`, `description TEXT NOT NULL`, `tool_calls LONGTEXT NULL`, `raw_output LONGTEXT NULL`, `validated_result LONGTEXT NULL`, `prefill_notes LONGTEXT NULL`, `error TEXT NULL`, `latency_ms INT UNSIGNED NULL`, `input_tokens INT UNSIGNED NULL`, `output_tokens INT UNSIGNED NULL` |

Decisions: L1 and L2 are not stored on `work_orders` (derived from L3, so they can never be inconsistent). Every FK not marked CASCADE is `ON DELETE RESTRICT ON UPDATE RESTRICT` (this includes the four CR FKs); give FKs explicit names `fk_<table>_<column>`. `tool_calls`, `validated_result`, `prefill_notes` hold JSON text with `CHECK (col IS NULL OR JSON_VALID(col))`; `raw_output` is free text (the model may return non-JSON). Status history order is `changed_at, id`. No `revoked_at`: `api-logout` deletes the token row. After the CR the only CHECK constraints in the schema are `chk_statuses_is_closed`, `chk_users_is_service_account` and the three `chk_ai_prefill_log_*`.

Test support, `tests/lib/db.php` (exists, unchanged by the CR): `test_db_name_is_disposable`, `test_db_skip_reason`, `test_db_connect` (no session `sql_mode` change), `test_db_split_sql`, `test_db_apply_sql_file`. Helpers never issue `CREATE/DROP DATABASE` or `USE`, and never print the password.

`tests/db/db_helper_test.php` (always runs, no connection), CR changes: the forbidden-pattern list drops `/\bINSERT\b/i`; instead assert that the statements starting with `INSERT` are exactly two, starting with `INSERT INTO statuses (` and `INSERT INTO priorities (`; also forbid `/\bENUM\s*\(/i` and `/\bAS\s+BINARY\b/i` in statements; CREATE TABLE count is 12. Unchanged: name guard, splitter, no BOM, valid UTF-8, semicolon rules.

`tests/db/schema_test.php` (skip logic unchanged), CR changes: `$expectedTables` has 12 names; `$seedMinimal` inserts the L3 with `default_priority_id = 2`; `$insertWorkOrder` takes `status_id` (default 1) and `priority_id` (default 2). Tests:
1. Applies twice; 12 tables; InnoDB + `utf8mb4_unicode_ci` per table; `statuses` count 5, `priorities` count 3, all other tables 0.
2. FK map (`KEY_COLUMN_USAGE` ⋈ `REFERENTIAL_CONSTRAINTS`) equals the old 11 entries plus `level3_typologies.default_priority_id`, `work_order_status_history.status_id`, `work_orders.priority_id`, `work_orders.status_id` (all `RESTRICT/RESTRICT`, CR names); `work_orders` has no `level1_typology_id`, `level2_typology_id`, `status`, `priority` column; history has no `status`; L3 has no `default_priority`; `information_schema.CHECK_CONSTRAINTS` for `DATABASE()` lists exactly the five CHECK names above.
3. Typology tree (unchanged apart from `default_priority_id`).
4. Work order references (unchanged apart from the new columns; read-back asserts `status_id` 1).
5. (New, Scenario 4a) Lookup rows fixed and byte-exact: exact rows by id, `HEX(name)` = `strtoupper(bin2hex(<PHP literal>))`, `COLUMNS.COLLATION_NAME` of both `name` columns is `utf8mb4_unicode_ci`, the look-alike 1062 cases, duplicate id / sort_order 1062, `is_closed = 2` 4025.
6. (Replaces "Spanish status and priority round-trip ... (4025)", Scenario 4b) assert `sql_mode` non-strict; FK acceptance and 1452 / 1048 / 1451 cases; `UPDATE work_orders SET status_id = 99` → 1452; history cascades with its work order.
7. AI Prefill log JSON (unchanged).
8. Users defaults, unique username, token cascade; re-applies the schema and leaves lookups populated (5/3) and every other table empty.

## Expected File Changes

CR amendment (files already exist from the accepted implementation):

- `db/schema.sql` — modify; CR tables, rows, FKs, drop/create order, header comment.
- `tests/db/db_helper_test.php` — modify; INSERT rule, ENUM/BINARY ban, 12 CREATEs.
- `tests/db/schema_test.php` — modify; tests 1–8 above.
- `ARCHITECTURE.md`, `CONSTRAINTS.md`, `docs/domain-model.md`, `docs/technical-discovery.md` — update (below).
- `feature_list.json` — only the `db-schema` entry (evidence; update `notes` so the "Deviation from spec: CAST(col AS BINARY)" sentence is marked superseded). `PROGRESS.md` — append a `db-schema change request` section.
- Not touched: `tests/lib/db.php`, `init.sh`, `AGENTS.md`, `CONTEXT.md`, anything in `src/` or `public/`.

## Visual Design Impact

- UI involved: no. Design source: not applicable. New design artifact: no.

## Durable Documentation Impact

- `ARCHITECTURE.md`: update the Database section — Tables (12, with the two lookup tables); Naming (lookup tables have fixed ids, no AUTO_INCREMENT); Integrity (add the four RESTRICT FKs to `statuses` / `priorities`); replace the "Domain values" bullet (lookup tables, fixed ids 1–5 / 1–3, `is_closed`, `sort_order`, `utf8mb4_unicode_ci` unique names reject look-alikes, inserted only by `db/schema.sql`, app refers by id, FK error 1452 instead of CHECK 4025); Lifecycle bullet (transitions in PHP; `is_closed` is data); Drop-and-create (re-inserts the lookup rows, reverse-dependency drop order); Tests (schema left with lookup rows, other tables empty).
- `CONSTRAINTS.md`: update — **replace** the rule "MUST store status and priority as the exact Spanish strings ... `CHECK (CAST(col AS BINARY) IN (...))`" with: **MUST** store status and priority only as integer FKs (`status_id`, `priority_id`, `default_priority_id`) to `statuses` / `priorities`, whose rows (fixed ids, exact Spanish names, `is_closed`, `sort_order`) are inserted only by `db/schema.sql`; **MUST NOT** insert, update or delete those rows anywhere else (seed, app, MCP; only the schema tests may attempt such writes, as failing probes on a disposable `hm_*` database that is re-applied afterwards) or reintroduce text/ENUM status or priority columns; code refers to them by id; transition rules live in PHP. Reason: user decision 2026-11-27; FKs hold regardless of the non-strict `sql_mode`; the `utf8mb4_unicode_ci` unique names reject case/accent/trailing-space look-alikes; fixed ids let seed and API rely on them. The other three DB rules stay as they are.
- `AGENTS.md`: not needed — startup path and commands unchanged.
- `docs/domain-model.md`: update — Core Concepts rows for **Status** (name, is closed, sort order) and **Priority** (name, sort order); the values line states they are fixed reference data with stable ids; Work Order, Status History Entry and L3 default priority reference them; Lifecycles notes that transitions are enforced by the API, not the database.
- `docs/technical-discovery.md`: update the Data and Storage sentence to list the status and priority lookup tables (fixed rows in `db/schema.sql`, not in the seed).
- `CONTEXT.md`: not needed — terms and values unchanged.

## Implementation Plan

1. Confirm `hm_db_schema` exists (`SHOW DATABASES LIKE 'hm_db_schema'`); it currently holds the pre-CR schema, which the new script drops.
2. Edit `db/schema.sql` per the CR; apply it twice with the mysql client; check the lookup hex.
3. Edit `tests/db/db_helper_test.php` and `tests/db/schema_test.php`; run `php tests/run.php db`.
4. Update the docs; run `./init.sh`; record evidence.

## Implementation Tasks

- [ ] `db/schema.sql`: add `statuses`/`priorities` + fixed-row INSERTs, replace the 4 columns, remove the 4 CHECKs and `ix_work_orders_status`, new drop/create order, new header comment.
- [ ] `tests/db/db_helper_test.php`: CR changes.
- [ ] `tests/db/schema_test.php`: CR tests 1–8.
- [ ] Update `ARCHITECTURE.md`, `CONSTRAINTS.md`, `docs/domain-model.md`, `docs/technical-discovery.md`.
- [ ] Run verification; record evidence in `feature_list.json` (db-schema only) and `PROGRESS.md`.

## Verification Plan

All commands from the repo root in Git Bash; `--defaults-extra-file` must be the first option; always name `hm_db_schema` explicitly and nothing else.

- Create once (no-op if it exists): `/c/xampp/mysql/bin/mysql.exe --defaults-extra-file=config/mysql.cnf -e "CREATE DATABASE IF NOT EXISTS hm_db_schema CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"`.
- Apply twice: `/c/xampp/mysql/bin/mysql.exe --defaults-extra-file=config/mysql.cnf hm_db_schema < db/schema.sql; echo "exit $?"` → `exit 0` both times.
- Inspect: `... hm_db_schema -e "SHOW TABLES; SHOW CREATE TABLE work_orders\G"` → 12 tables; `status_id`/`priority_id` FKs, no status/priority CHECKs.
- Byte-exact lookups after the mysql-client apply: `... hm_db_schema -e "SELECT id, name, HEX(name), is_closed, sort_order FROM statuses ORDER BY id; SELECT id, name, HEX(name), sort_order FROM priorities ORDER BY id"` → exactly the CR tables; id 3 hex `456E207265736F6C756369C3B36E`.
- Manual probes: `... hm_db_schema -e "INSERT INTO level2_typologies (level1_typology_id, name) VALUES (999, 'x')"` → `ERROR 1452`; `... hm_db_schema -e "INSERT INTO statuses (id, name, is_closed, sort_order) VALUES (9, 'En resolucion', 0, 9)"` → `ERROR 1062`. Re-apply the schema afterwards.
- `php tests/run.php db` → 11 tests (3 helper + 8 schema), 0 failures.
- Guard: unchanged from the accepted run (in-process `APP_ROOT` method in Implementation Notes is acceptable); never point config at another database.
- `./init.sh` → exit 0, 16 files, 31 tests (30 + 1 new), 0 failures, no long-running process. `init.sh` unchanged (LF, 100755).
- No dev server needed (no HTTP surface). If one is started for any reason, use `php -S 127.0.0.1:8101 -t public` and stop it.
- E2E: not applicable (no user-visible or API flow).
- Isolation and secrets: only `hm_db_schema` touched; no password in diffs; `git status` shows no `config/config.php` or `config/mysql.cnf`.

## Evidence To Capture

- Both mysql apply runs with exit codes; `SHOW TABLES` (12); the lookup `SELECT ... HEX(name)` output after the mysql-client apply.
- `php tests/run.php db` PASS lines and summary.
- `./init.sh` summary lines (files, tests, exit code).
- MariaDB version (10.4.27).

## Validator Checklist

- [ ] Only the files listed in Expected File Changes changed; no `src/` DB code, no triggers/procedures, no seed rows besides the fixed lookup rows.
- [ ] `db/schema.sql` names no database, stays splittable, and runs twice against `hm_db_schema` with exit 0.
- [ ] 12 tables; lookup rows exactly as in the CR tables (ids, names, `is_closed`, `sort_order`), `En resolución` hex `...C3B36E` after both the mysql-client and the PDO apply.
- [ ] No `status`/`priority`/`default_priority` text columns and no CHECK other than the five listed; four new FKs are `RESTRICT/RESTRICT` with CR names; drop order is reverse dependency.
- [ ] Tests prove 1452 (unknown status/priority ids and old FK cases), 1451 (referenced lookup rows and typology/location/user), 1048 (NULL ids), 1062 (look-alike lookup names), 4025 (`is_closed`, JSON, `is_service_account`).
- [ ] Guard skips cleanly without config or with a non-`hm_` name; no other database was touched.
- [ ] `./init.sh` exits 0; `init.sh` unchanged.
- [ ] `ARCHITECTURE.md`, `CONSTRAINTS.md` (old CHECK rule replaced, not kept alongside), `docs/domain-model.md`, `docs/technical-discovery.md` match the CR; `feature_list.json` changes limited to `db-schema`; `PROGRESS.md` has an appended section; no commit or push.

## Implementation Notes

- **CHECK collation (spec gap fixed).** Superseded (CR 2026-11-27): status/priority CHECKs no longer exist. Historical: with `utf8mb4_unicode_ci`, a plain `CHECK (status IN (...))` accepted `'En resolucion'`, so the CHECKs compared `COLLATE utf8mb4_bin`.
- **Byte comparison (validator revise, 2026-11-27).** Superseded (CR 2026-11-27). Historical: `utf8mb4_bin` is PAD SPACE on MariaDB 10.4, so the CHECKs moved to `CAST(col AS BINARY) IN (...)`. The same collation facts now motivate keeping `utf8mb4_unicode_ci` on the lookup `name` columns (see CR "Name collation").
- **Missing database fails, not skips.** With a valid `hm_*` `db.name` and no such database, the live DB tests fail with `Unknown database`. `AGENTS.md` puts the database step before `./init.sh`, and `init.sh` prints the creation hint before the tests too. (Still valid.)
- **Guard proof without renaming the real config.** Renaming `config/config.php` was denied by the session's permissions. Instead the real `tests/db/schema_test.php` was loaded in-process with `APP_ROOT` pre-defined to a root without `config/` (→ SKIP, 0 tests) and to a temporary fixture with `db.name = hotel_maintenance` (→ SKIP, no connection attempted). The fixture was removed afterwards. (Still valid.)
- Constraint, unique and index names follow `fk_`/`uq_`/`chk_`/`ix_<table>_...`. Additional coverage from the accepted run (engine/collation per table, FK rules, L2 duplicate name, referenced location/creator deletes, cascades, `is_service_account = 2`, JSON per column) stays.
