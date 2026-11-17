# Feature Implementation Spec: Runnable project skeleton with config and test runner

## Source Feature

- `id`: bootstrap-project-skeleton
- `area`: bootstrap
- `depends_on`: none
- `status`: not_started (at planning time)
- `source`: `feature_list.json`

## Goal

Turn the documentation-only repo into a runnable PHP 8 baseline: a web root with a placeholder frontend page and a placeholder API entry point, a git-ignored config file created from a committed example, a tiny hand-written test runner (`php tests/run.php`), and a real `./init.sh` gate that syntax-checks all project PHP and runs the tests. Later features build on this layout without re-deciding it.

## Non-Goals

- No DB schema, seed data or DB connection code (`db-schema`, `db-seed-data`).
- No request routing, method checks or error response shape (`api-post-json-router`). The API entry point returns one fixed JSON document. No `.htaccess` rewrite and no `php -S` router script yet.
- No login, tokens, MCP server, AI connector or LLM calls.
- No real UI screens, menu, app CSS tokens or JavaScript (`ui-*` features).
- No Composer, npm, PHPUnit or any library other than the vendored W3.CSS file.
- No edits to the XAMPP installation by the implementer without explicit user approval (see Apache below).

## Job Story

When I (developer or agent) open this repo in a new session,
I want one command that proves the PHP code parses and the tests pass, and a documented way to serve the app,
so I can start the next feature from a known-good baseline.

## Users And Permissions

- Developer/agent: runs `./init.sh`, `php tests/run.php`, and the dev server. No end-user roles are involved.

## Acceptance Scenarios

### Scenario 1: Gate passes on a fresh clone
Given a clone with no `config/config.php`
When `./init.sh` runs in Git Bash
Then it syntax-checks every project `.php` file (excluding `.agents/`, `.claude/`, `.git/`), runs `php tests/run.php`, prints a warning that `config/config.php` is missing, and exits 0.

### Scenario 2: Test runner reports results and fails correctly
Given the test suite
When `php tests/run.php` runs
Then it prints one line per test, a summary `N tests, 0 failures`, and exits 0; if any test throws, it prints `FAIL <file> :: <name>: <message>` and exits 1; if no tests are found it exits 1.

### Scenario 3: Built-in server serves the placeholder page and API entry point
Given `php -S 127.0.0.1:8080 -t public` is running
When the browser opens `http://127.0.0.1:8080/` and a client POSTs `{}` to `http://127.0.0.1:8080/api/`
Then the page shows the Spanish placeholder styled with the local W3.CSS file, and the API answers 200 with `Content-Type: application/json` and `{"ok":true,"service":"hotel-maintenance-api"}`.

### Scenario 4: XAMPP Apache serves the same files
Given the Apache Alias snippet from `config/apache.conf.example` is included in XAMPP and Apache restarted
When the browser opens `http://localhost/devexpertai/` and POSTs to `http://localhost/devexpertai/api/`
Then the same placeholder page and JSON are returned.

### Scenario 5: Secrets stay out of git
Given a developer copies `config/config.example.php` to `config/config.php` and fills in secrets
When they run `git status`
Then `config/config.php` is not listed, and `git ls-files config` lists only the example files.

### Scenario 6: Syntax error blocks the gate
Given a project `.php` file with a parse error
When `./init.sh` runs
Then it prints the failing file and exits non-zero without claiming success.

## Repository Research

### Files Inspected

- `AGENTS.md`, `PROGRESS.md`, `feature_list.json`, `CONTEXT.md`, `DESIGN.md` (tokens only), `init.sh` (pre-bootstrap, echoes only), `.gitignore` (empty), `.gitattributes` (`*.sh eol=lf`).
- `docs/build-brief.md`, `docs/technical-discovery.md`, `docs/risks-and-open-questions.md`, `docs/adr/0001-php-backend-runs-agent-loop.md`.
- Later features in `feature_list.json` that fix paths: `db/schema.sql`, `db/seed.sql`, `mcp/server.php`, `/api/<endpoint>`.
- No application code, `docs/specs/`, `ARCHITECTURE.md` or `CONSTRAINTS.md` exist yet.

### Environment Findings (this machine, read-only)

- `php` on PATH in Git Bash is `/c/xampp/php/php` → PHP 8.2.0 ZTS x64, `C:\xampp\php\php.ini`; extensions include curl, json, mbstring, openssl, pdo_mysql, mysqli. `zend.assertions=1`.
- XAMPP at `C:/xampp`: Apache 2.4.54 is running on port 80 (mod_php `php8apache2_4.dll`, same PHP), MariaDB 10.4.27 running as a Windows service on 3306. `mysql` is not on the Git Bash PATH (`C:/xampp/mysql/bin/mysql.exe`).
- DocumentRoot is `C:/xampp/htdocs`; the repo is on the Desktop, outside htdocs. Existing local projects are wired via `<Directory>`+`<VirtualHost *:443>` blocks or an `Include` of a per-project conf from `C:/xampp/apache/conf/extra/httpd-vhosts.conf` (e.g. the tunesmiths project). `mod_alias` and `mod_rewrite` are loaded.
- Probe: `proc_open([PHP_BINARY, '-S', '127.0.0.1:<free port>', '-t', <dir>], ...)` from PHP on this machine became ready in ~0.35 s, answered a POST with JSON, and `proc_terminate` left no `php.exe` behind (3/3 runs). The **array form** of `proc_open` is required: the string form goes through `cmd.exe` and terminating it can orphan the server.
- `https://www.w3schools.com/w3css/5/w3.css` returns W3.CSS 5.01 (March 11 2026, 24,743 bytes, starts with a UTF-8 BOM). The site returns **403 to curl's default User-Agent**; a browser User-Agent string gets 200.

### Existing Patterns To Follow

- Shell scripts are LF (`.gitattributes`). `init.sh` is tracked as mode 100755.
- UI copy in Spanish; code/docs in English; canonical terms from `CONTEXT.md`.

### Current Gaps

- No web root, entry points, config, tests or real gate. This feature creates them.

## Technical Approach

**Decision: test tooling.** Plain PHP test files with a hand-written runner, `php tests/run.php`. PHPUnit is not approved. Do not use PHP's `assert()` (depends on `zend.assertions`); use the helpers in `tests/lib/assert.php`.

**Layout** (paths for later features are reserved, not created now):

```
public/                  web root (only this folder is served)
  index.html             placeholder page
  api/index.php          API entry point (placeholder JSON)
  assets/vendor/w3css/w3.css + SOURCE.txt
src/                     PHP code outside the web root, namespace App\ → src/
  bootstrap.php          APP_ROOT, error settings, autoloader
  Config.php             App\Config
config/
  config.example.php     committed; config.php is git-ignored
  apache.conf.example    committed; apache.conf is git-ignored
tests/
  run.php, lib/assert.php, lib/dev_server.php, bootstrap/*_test.php
db/   (later: schema.sql, seed.sql)   mcp/ (later: server.php)   logs/ (later, ignored)
```

**Serving.** Two ways, same files, relative URLs only:
- Dev and automated checks: PHP built-in server, `php -S 127.0.0.1:8080 -t public`. `/` serves `index.html`; `/api/` serves `api/index.php` through the directory index. Clean `/api/<endpoint>` URLs come with `api-post-json-router`. Note for that feature: under an Apache `Alias`, `.htaccess` rewrites need a `RewriteBase` or base detection, and `php -S` will need a router script.
- XAMPP: an `Alias /devexpertai "<PROJECT_ROOT>/public"` plus a `<Directory>` block (`Options -Indexes +FollowSymLinks`, `AllowOverride All`, `Require local`) in `config/apache.conf.example`. The user copies it to `config/apache.conf` with the real path and adds `Include "<PROJECT_ROOT>/config/apache.conf"` to `httpd-vhosts.conf`, the same pattern as their other projects. URL: `http://localhost/devexpertai/`. This changes shared XAMPP config and needs an Apache restart, so the implementer asks the user before doing it, or leaves it as a documented manual step.
- The frontend must only use relative URLs (`assets/...`, `api/...`) so it works both at `/` (php -S) and under `/devexpertai/` (Apache).

**W3.CSS source: vendored, not CDN.** This is a local/offline PoC on XAMPP. Vendoring keeps the app working offline, pins the version and avoids a third-party request on every page load. Download `https://www.w3schools.com/w3css/5/w3.css` (official, W3.CSS 5.01) with a browser User-Agent (`curl -A "Mozilla/5.0 ..."`), keep the file byte-for-byte unmodified, and write `SOURCE.txt` with the URL, version header line, download date and SHA-256 (`sha256sum`).

**Placeholder page** (`public/index.html`): `<!DOCTYPE html>`, `lang="es"`, viewport meta, `<link rel="stylesheet" href="assets/vendor/w3css/w3.css">`, `<body data-app="hotel-maintenance">`, one W3.CSS container with the heading "Mantenimiento del hotel" and the text "Aplicación en construcción." No JS, no custom CSS.

**API entry point** (`public/api/index.php`): requires `../../src/bootstrap.php`, sets `Content-Type: application/json; charset=utf-8`, echoes `json_encode(['ok' => true, 'service' => 'hotel-maintenance-api'])`. It does not load config, so it works without `config/config.php`. `api-post-json-router` replaces its body.

**Bootstrap** (`src/bootstrap.php`): `declare(strict_types=1)`; define `APP_ROOT` (repo root); `error_reporting(E_ALL)`; `ini_set('display_errors', '0')` and `log_errors=1` (no HTML errors inside JSON); `spl_autoload_register` that maps `App\Foo\Bar` to `src/Foo/Bar.php`. Must be safe to require twice (`require_once` usage, guard on `defined('APP_ROOT')`).

**Config.** `config/config.example.php` returns a PHP array (a PHP file, so it is never served as text even if misplaced). Placeholder values only, no real secrets:
- `db`: `host` 127.0.0.1, `port` 3306, `name` hotel_maintenance, `user` root, `password` '', `charset` utf8mb4
- `llm`: `default_provider` 'anthropic'; `providers.anthropic` {`base_url` https://api.anthropic.com, `api_key` 'CHANGE_ME', `model` 'CHANGE_ME'}; `providers.devexpert` {`base_url` https://inference.devexpert.io, `api_key` 'CHANGE_ME', `model` 'chat'}
- `mcp`: `api_key` 'CHANGE_ME'
- `service_account`: `username` 'mcp-service', `password` 'CHANGE_ME'
- `auth`: `token_ttl_hours` 8
- `log`: `dir` 'logs'

`App\Config::load(string $path): array` throws `RuntimeException` if the file is missing (message names the path and says to copy `config/config.example.php`) or does not return an array. `App\Config::loadDefault(): array` loads `APP_ROOT/config/config.php`. No caching or nested getters yet.

**Test runner** (`tests/run.php`): requires `src/bootstrap.php` and `tests/lib/*.php`; finds `tests/**/*_test.php` recursively, sorted; each file `return`s `array<string, callable>`. Runs each closure in try/catch(`Throwable`); prints `PASS <file> :: <name>` or `FAIL <file> :: <name>: <message>`; prints `N tests, F failures`; exits 1 on any failure, if a file does not return an array, or if zero tests ran. An optional argv[1] substring filters by file or test name.
- `tests/lib/assert.php`: `final class TestFailure extends Exception`; functions `assert_true`, `assert_same`, `assert_contains` (string contains), `assert_throws(callable, string $class)`; each throws `TestFailure` with expected/actual in the message.
- `tests/lib/dev_server.php`: `start_dev_server(string $docroot): array` (free port from `stream_socket_server('tcp://127.0.0.1:0')`, array-form `proc_open` with `PHP_BINARY`, stdout/stderr to `NUL` on Windows or `/dev/null` elsewhere, poll `fsockopen` up to 5 s, throw if not ready) and `stop_dev_server(array $server)` (`proc_terminate` + `proc_close`). Plus `http_request(string $method, string $url, ?string $body): array{status:int, headers:array, body:string}` using `file_get_contents` with a stream context (`ignore_errors`, 5 s timeout). Tests always stop the server in `finally`.

**Gate** (`init.sh`, replaces the current file): `#!/usr/bin/env bash`, `set -euo pipefail`, `cd "$(dirname "$0")"`. Steps:
1. Fail with a PATH hint (`C:/xampp/php`) if `php` is missing; print the PHP version; fail if `PHP_VERSION_ID < 80000`.
2. Syntax check: `find . \( -path ./.git -o -path ./.agents -o -path ./.claude \) -prune -o -name '*.php' -print`, run `php -l` on each, count files and failures (do not rely on `set -e` inside a pipeline loop); fail if any file fails or if zero files were found.
3. `php tests/run.php`; propagate its exit code.
4. Warn (do not fail) if `config/config.php` is missing.
5. Print manual follow-ups: `php -S 127.0.0.1:8080 -t public`, Apache URL `http://localhost/devexpertai/` (after the Include step), and that DB commands arrive with `db-schema`.
It never starts a long-running process; the only server is the short-lived one inside the serving test.

**.gitignore** (currently empty): `/config/config.php`, `/config/apache.conf`, `/logs/`, `*.log`, `/tmp/`, `Thumbs.db`, `desktop.ini`, `.DS_Store`.

## Expected File Changes

- `public/index.html` — create; placeholder page.
- `public/api/index.php` — create; placeholder JSON entry point.
- `public/assets/vendor/w3css/w3.css`, `SOURCE.txt` — create; vendored official file and provenance.
- `src/bootstrap.php`, `src/Config.php` — create.
- `config/config.example.php`, `config/apache.conf.example` — create.
- `tests/run.php`, `tests/lib/assert.php`, `tests/lib/dev_server.php` — create.
- `tests/bootstrap/runner_test.php`, `tests/bootstrap/config_test.php`, `tests/bootstrap/serving_test.php` — create.
- `init.sh` — replace; keep LF and mode 100755.
- `.gitignore` — modify.
- Docs listed below.

## Visual Design Impact

- UI involved: minimal (one placeholder page). Design source: `DESIGN.md`. Use stock W3.CSS classes only; the design tokens arrive with the first `ui-*` feature. New design artifact required: no.

## Durable Documentation Impact

- `ARCHITECTURE.md`: create — directory layout, web root boundary (only `public/` served), `App\` autoload convention, the two serving modes and the relative-URL rule, config loading.
- `CONSTRAINTS.md`: create — MUST: only `public/` is web-served; frontend uses relative URLs only; secrets only in `config/config.php`; tests are plain PHP run by `tests/run.php` (no PHPUnit, no `assert()`); W3.CSS stays vendored and unmodified; spawn PHP subprocesses with array-form `proc_open`.
- `AGENTS.md`: update — Startup Workflow: gate is real; add the dev-server command and the config copy step. Also align step 3 with the skill wording on dependency status if the user wants (see report).
- `PROGRESS.md`: update — verified state, real verification path, evidence.
- `docs/technical-discovery.md`: update Testing and Deployment sections (plain PHP runner decided; serving via `php -S` and Apache Alias).
- `docs/risks-and-open-questions.md`: move "Test tooling" from Implementation-Time Questions to a resolved/decided entry: plain PHP scripts + `php tests/run.php`; PHPUnit not approved.

## Implementation Plan

1. `.gitignore`, `config/` examples, `src/bootstrap.php`, `src/Config.php`.
2. Test runner and libs, then `runner_test.php` and `config_test.php`; run `php tests/run.php`.
3. `public/` files and vendored W3.CSS with `SOURCE.txt`; then `serving_test.php`.
4. Replace `init.sh`; run it; prove the failure paths once (Scenario 2 and 6) with temporary files, then delete them.
5. Ask the user before doing the Apache Include step; if approved, do it and check Scenario 4, otherwise record it as a pending manual check.
6. Update the docs, `PROGRESS.md` and `feature_list.json` evidence.

## Implementation Tasks

- [ ] Write `.gitignore` entries.
- [ ] Create `config/config.example.php` (keys above) and `config/apache.conf.example` (with `<PROJECT_ROOT>` placeholder and the Include instructions as comments).
- [ ] Create `src/bootstrap.php` and `src/Config.php`.
- [ ] Create `tests/lib/assert.php`, `tests/lib/dev_server.php`, `tests/run.php`.
- [ ] `runner_test.php`: each assertion helper throws `TestFailure` on mismatch and passes on match.
- [ ] `config_test.php`: example loads and has every required key path; `Config::load` on a missing path throws `RuntimeException` whose message mentions `config.example.php`; `.gitignore` contains the line `/config/config.php`.
- [ ] Download W3.CSS 5.01, write `SOURCE.txt` with SHA-256.
- [ ] Create `public/index.html` and `public/api/index.php`.
- [ ] `serving_test.php` (one server for all three checks): `GET /` → 200, body has `data-app="hotel-maintenance"` and `href="assets/vendor/w3css/w3.css"`; `GET /assets/vendor/w3css/w3.css` → 200, body contains `W3.CSS 5.01`; `POST /api/` with `{}` → 200, `Content-Type` starts with `application/json`, decoded `ok === true`.
- [ ] Replace `init.sh`; check `git ls-files -s init.sh` still shows 100755.
- [ ] Update docs listed in Durable Documentation Impact.

## Verification Plan

- `./init.sh` in Git Bash → syntax check lists N files with 0 errors, runner prints `N tests, 0 failures`, exit 0. Run it without `config/config.php` (warning only).
- `php tests/run.php` → exit 0; `php tests/run.php serving` runs only the serving tests.
- Temporarily add a failing `*_test.php` → runner exits 1 with a `FAIL` line; temporarily add a parse error `.php` file → `./init.sh` exits non-zero naming it. Remove both.
- After the serving test, `tasklist | grep -i php` shows no leftover `php.exe` started by the test.
- `cp config/config.example.php config/config.php && git status --short config/` → nothing listed; `git ls-files config` → only the two example files.
- Manual: `php -S 127.0.0.1:8080 -t public`, open `/` in a browser at 360px width → placeholder visible with W3.CSS styling; `curl -s -X POST -d '{}' http://127.0.0.1:8080/api/` → the JSON.
- Manual (needs the user's approval for the Apache Include): `http://localhost/devexpertai/` and `curl -s -X POST -d '{}' http://localhost/devexpertai/api/`.
- E2E: no persistent E2E harness exists. The serving test is the automated end-to-end smoke check for this slice.

## Evidence To Capture

- `./init.sh` output (file count, test summary, exit code) in `PROGRESS.md`.
- Output of the deliberate failure runs (runner exit 1, parse error caught).
- `git status` / `git ls-files config` output for the ignored config.
- `SOURCE.txt` contents (URL, version, SHA-256).
- Apache check result, or "pending user approval" if not done.

## Validator Checklist

- [ ] Only the files and docs listed above changed; no schema, routing logic, auth, MCP, AI or UI screens.
- [ ] No Composer/npm/PHPUnit files; only W3.CSS vendored, unmodified, with `SOURCE.txt`.
- [ ] `./init.sh` is LF, 100755, `set -euo pipefail`, starts no long-running process, exits 0 on clean repo and non-zero on parse error or test failure.
- [ ] `php tests/run.php` exits 1 on a failing test and on zero tests.
- [ ] Serving test uses array-form `proc_open` and always stops the server; no orphan `php.exe`.
- [ ] Frontend references are relative; only `public/` is the web root.
- [ ] `config/config.php` ignored; example has placeholders only, no real secrets.
- [ ] Scenarios 1–3, 5, 6 pass; Scenario 4 passed or recorded as pending user approval.
- [ ] `ARCHITECTURE.md`, `CONSTRAINTS.md`, `AGENTS.md`, `PROGRESS.md`, technical discovery and risks docs updated; `feature_list.json` evidence recorded.

## Implementation Notes (added by the implementer, 2026-11-17)

- `.gitattributes` gained `/public/assets/vendor/** -text` (not in Expected File Changes). Reason: this machine has `core.autocrlf=true` and `* text=auto`, which would convert the LF-only `w3.css` to CRLF on checkout and break the recorded SHA-256. This keeps the vendored file byte-for-byte.
- `tests/lib/dev_server.php` also provides `http_header(array $headers, string $name): ?string` to read a response header case-insensitively.
- `config/apache.conf.example` adds `DirectoryIndex index.html index.php` inside the `<Directory>` block so `/devexpertai/api/` resolves to `api/index.php` regardless of the global DirectoryIndex.
- Scenario 4 (XAMPP Apache): the Include step was performed by the orchestrator with the user's approval (not by the implementer); results are recorded in `feature_list.json` and `PROGRESS.md`.
