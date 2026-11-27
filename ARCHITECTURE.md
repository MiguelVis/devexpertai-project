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
  lib/                   assert helpers, short-lived dev server + HTTP helpers
  <area>/*_test.php      test files, each returns array<string, callable>
db/                      reserved: schema.sql, seed.sql (db-schema, db-seed-data)
mcp/                     reserved: server.php (stdio MCP server)
logs/                    reserved, git-ignored runtime logs
```

## Boundaries

- **Web root.** Only `public/` is exposed. Everything else (`src/`, `config/`, `tests/`, `db/`, `mcp/`) stays outside it and is reached via `require` from entry points.
- **Bootstrap.** Every PHP entry point (API, CLI scripts, tests) starts with `require_once <root>/src/bootstrap.php`. It defines `APP_ROOT`, sets `error_reporting(E_ALL)`, `display_errors=0`, `log_errors=1` (no HTML errors inside JSON), and registers the autoloader mapping `App\Foo\Bar` to `src/Foo/Bar.php`. It is safe to require twice.
- **Config.** `App\Config::loadDefault()` loads `APP_ROOT/config/config.php`, a PHP file returning an array (keys: `db`, `llm` with `default_provider` and `providers.{anthropic,devexpert}`, `mcp`, `service_account`, `auth`, `log`). `App\Config::load($path)` throws `RuntimeException` if the file is missing or does not return an array. Code that does not need config (e.g. the placeholder API entry point) must not load it.
- **Database account.** The app, tests and agent sessions connect as the dedicated MariaDB user `hm_app@localhost`, which has `ALL` only on `hotel_maintenance` and on `hm\_%` (per-feature databases such as `hm_db_schema`). The MariaDB `root` account is not used by the project. The password lives only in the git-ignored `config/config.php` (`db.password`) and `config/mysql.cnf` (mysql client, used as `mysql.exe --defaults-extra-file=config/mysql.cnf ...`, which must be the first option).

## Serving Modes

Both serve the same `public/` folder:

1. **PHP built-in server** (dev and automated tests): `php -S 127.0.0.1:8080 -t public`. `/` serves `index.html`, `/api/` serves `api/index.php` via the directory index.
2. **XAMPP Apache**: `Alias /devexpertai "<PROJECT_ROOT>/public"` plus a `<Directory>` block, from `config/apache.conf.example`, included from `C:/xampp/apache/conf/extra/httpd-vhosts.conf`. URL: `http://localhost/devexpertai/`.

Because the app lives at `/` in one mode and `/devexpertai/` in the other, the frontend uses **relative URLs only** (`assets/...`, `api/...`).

Note for `api-post-json-router`: clean `/api/<endpoint>` URLs need a rewrite. Under an Apache `Alias` an `.htaccess` rewrite needs `RewriteBase` (or base detection), and `php -S` needs a router script.

## Verification

- `./init.sh` (Git Bash): PHP >= 8 check, `php -l` on every project `.php` file (excluding `.git/`, `.agents/`, `.claude/`), then `php tests/run.php`. Warns if `config/config.php` is missing. Starts no long-running process.
- `php tests/run.php [filter]`: runs `tests/**/*_test.php`, prints `PASS`/`FAIL` lines and `N tests, F failures`, exits 1 on any failure, a file that does not return an array, or zero tests.
- Serving tests start a short-lived `php -S` on a free port via `tests/lib/dev_server.php` (array-form `proc_open`) and stop it in `finally`.
