# Architecture

Plain PHP 8 (no frameworks, no Composer), MySQL/MariaDB, static HTML + W3.CSS + plain JavaScript, served locally on Windows with XAMPP. Established by feature `bootstrap-project-skeleton`.

## Directory Layout

```
public/                  web root: the ONLY folder served over HTTP
  index.html             frontend entry page (placeholder for now)
  api/index.php          API entry point (placeholder JSON; routing arrives with api-post-json-router)
  assets/vendor/w3css/   vendored, unmodified W3.CSS 5.01 + SOURCE.txt
  assets/vendor/material-symbols/  vendored, unmodified Material Symbols Outlined font + LICENSE + SOURCE.txt
  assets/vendor/source-sans-3/     vendored, unmodified Source Sans 3 (latin, variable weight) + OFL.txt + SOURCE.txt
  assets/img/brand/      hotel brand PNGs (favicon, logo-mark, logo-white, logo); project-owned, see DESIGN.md Brand
  assets/css/icons.css   @font-face + .material-symbols-outlined class for the local icon font
  assets/css/fonts.css   @font-face for the local Source Sans 3 text font
src/                     PHP application code, namespace App\ -> src/
  bootstrap.php          APP_ROOT, error settings, App\ autoloader
  Config.php             App\Config: loads config/config.php
config/
  config.example.php     committed template; config/config.php is the git-ignored real file
  apache.conf.example    committed XAMPP Alias snippet; config/apache.conf is git-ignored
  mysql.cnf.example      committed mysql client login template; config/mysql.cnf is git-ignored
tests/
  run.php                plain PHP test runner
  lib/                   assert helpers, short-lived dev server + HTTP helpers, db.php (guarded test DB helpers)
  <area>/*_test.php      test files, each returns array<string, callable>
db/
  schema.sql             drop-and-create schema for all tables (see Database)
                         reserved: seed.sql (db-seed-data)
mcp/                     reserved: server.php (stdio MCP server)
logs/                    reserved, git-ignored runtime logs
```

## Boundaries

- **Web root.** Only `public/` is exposed. Everything else (`src/`, `config/`, `tests/`, `db/`, `mcp/`) stays outside it and is reached via `require` from entry points.
- **Bootstrap.** Every PHP entry point (API, CLI scripts, tests) starts with `require_once <root>/src/bootstrap.php`. It defines `APP_ROOT`, sets `error_reporting(E_ALL)`, `display_errors=0`, `log_errors=1` (no HTML errors inside JSON), and registers the autoloader mapping `App\Foo\Bar` to `src/Foo/Bar.php`. It is safe to require twice.
- **Config.** `App\Config::loadDefault()` loads `APP_ROOT/config/config.php`, a PHP file returning an array (keys: `db`, `llm` with `default_provider` and `providers.{anthropic,devexpert}`, `mcp`, `service_account`, `auth`, `log`). `App\Config::load($path)` throws `RuntimeException` if the file is missing or does not return an array. Code that does not need config (e.g. the placeholder API entry point) must not load it.
- **Database account.** The app, tests and agent sessions connect as the dedicated MariaDB user `hm_app@localhost`, which has `ALL` only on `hotel_maintenance` and on `hm\_%` (per-feature databases such as `hm_db_schema`). The MariaDB `root` account is not used by the project. The password lives only in the git-ignored `config/config.php` (`db.password`) and `config/mysql.cnf` (mysql client, used as `mysql.exe --defaults-extra-file=config/mysql.cnf ...`, which must be the first option).

## Database

Established by feature `db-schema`. MariaDB 10.4 (XAMPP), InnoDB, `utf8mb4` / `utf8mb4_unicode_ci` on every table.

- **Tables** (`db/schema.sql`): `location_types`, `locations`, `level1_typologies`, `level2_typologies`, `level3_typologies` (with `default_priority`), `users` (with `is_service_account`), `auth_tokens`, `work_orders`, `work_order_status_history`, `ai_prefill_log`.
- **Naming:** `snake_case`, plural table names, `id INT UNSIGNED AUTO_INCREMENT` primary keys, foreign key columns `<entity>_id` (`creator_user_id`, `assignee_user_id`, `level3_typology_id`...), constraint names `fk_<table>_<column>`, `uq_...`, `chk_...`, `ix_...`.
- **Integrity:** foreign keys enforce L3 → L2 → L1 and work order → location / L3 typology / creator / assignee (nullable). All are `ON DELETE RESTRICT` except `auth_tokens.user_id` and `work_order_status_history.work_order_id` (`CASCADE`). Work orders store only the L3 typology; L1 and L2 are derived from it.
- **Domain values:** status (`Pendiente`, `Asignado`, `En resolución`, `Resuelto`, `Cancelado`) and priority (`Baja`, `Media`, `Alta`) are `VARCHAR` with `CHECK (CAST(col AS BINARY) IN (...))`: exact Spanish strings, compared byte by byte (case-, accent- and trailing-space-sensitive; `utf8mb4_bin` is not enough because it is PAD SPACE on MariaDB 10.4 and accepts `'Pendiente '`). Not ENUM: the server `sql_mode` is not strict, so a bad ENUM value would be stored as `''`. A CHECK violation is MariaDB error 4025.
- **AI Prefill log:** `tool_calls`, `validated_result`, `prefill_notes` are `LONGTEXT` with `JSON_VALID` checks; `raw_output` is free text.
- **Auth tokens:** only `token_hash` (hex SHA-256 of the token) is stored; logout deletes the row; `expires_at` is indexed.
- **Lifecycle rules are not in SQL** (allowed transitions, assignee required for `En resolución`, service accounts never creator/assignee): the API enforces them. No triggers, procedures, views or events.
- **Drop-and-create:** `db/schema.sql` drops and recreates every table (all rows are lost; the seed reloads data). It names no database. Apply from the repo root:
  `/c/xampp/mysql/bin/mysql.exe --defaults-extra-file=config/mysql.cnf hm_<feature> < db/schema.sql`
  (create the database once with `CREATE DATABASE IF NOT EXISTS hm_<feature> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`).
- **Script format:** each statement ends with `;` at the end of a line, comments are full `--` lines, no `;` inside comments or literals, no `DELIMITER`, so `tests/lib/db.php` can split it.
- **Tests:** `tests/lib/db.php` provides `test_db_skip_reason()`, `test_db_connect()` (PDO, `utf8mb4`, exceptions, server default `sql_mode`), `test_db_split_sql()` and `test_db_apply_sql_file()`. Live DB tests (`tests/db/schema_test.php`) run only when `config/config.php` exists and `db.name` matches `^hm_[a-z0-9_]+$`; otherwise the file prints one `SKIP` line and returns no tests. They re-apply the schema and leave it empty.

## Serving Modes

Both serve the same `public/` folder:

1. **PHP built-in server** (dev and automated tests): `php -S 127.0.0.1:8080 -t public`. `/` serves `index.html`, `/api/` serves `api/index.php` via the directory index.
2. **XAMPP Apache**: `Alias /devexpertai "<PROJECT_ROOT>/public"` plus a `<Directory>` block, from `config/apache.conf.example`, included from `C:/xampp/apache/conf/extra/httpd-vhosts.conf`. URL: `http://localhost/devexpertai/`.

Because the app lives at `/` in one mode and `/devexpertai/` in the other, the frontend uses **relative URLs only** (`assets/...`, `api/...`).

Note for `api-post-json-router`: clean `/api/<endpoint>` URLs need a rewrite. Under an Apache `Alias` an `.htaccess` rewrite needs `RewriteBase` (or base detection), and `php -S` needs a router script.

## Verification

- `./init.sh` (Git Bash): PHP >= 8 check, `php -l` on every project `.php` file (excluding `.git/`, `.agents/`, `.claude/`), then `php tests/run.php`. Warns if `config/config.php` is missing; if it exists, prints the database-creation hint before the tests (and again at the end). Starts no long-running process.
- `php tests/run.php [filter]`: runs `tests/**/*_test.php`, prints `PASS`/`FAIL` lines and `N tests, F failures`, exits 1 on any failure, a file that does not return an array, or zero tests.
- Live DB tests print `SKIP tests/db/schema_test.php: <reason>` (and the gate still passes) only when `config/config.php` is missing or its `db.name` does not match `^hm_[a-z0-9_]+$`. If the name is valid but the database does not exist, they FAIL (`Unknown database`) and the gate exits non-zero. Create the database once with the documented `CREATE DATABASE IF NOT EXISTS hm_<feature> ...` command (see Database); the tests apply the schema themselves.
- Serving tests start a short-lived `php -S` on a free port via `tests/lib/dev_server.php` (array-form `proc_open`) and stop it in `finally`.
