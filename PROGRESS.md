# Progress Log

## Current Verified State

- Repository root: `devexpertai-project/`
- Standard startup path: `./init.sh` (Git Bash on Windows)
- Standard verification path: `./init.sh` = PHP >= 8 check + `php -l` on all project `.php` files + `php tests/run.php`. Exits non-zero on any failure; starts no long-running process.
- Serving: `php -S 127.0.0.1:8080 -t public` (dev) or XAMPP Apache at `http://localhost/devexpertai/` (Include configured on this machine).
- Local config: copy `config/config.example.php` to `config/config.php` (git-ignored); the gate only warns if it is missing.
- Last accepted feature: `bootstrap-project-skeleton` (independent validator, 2026-11-17).
- Current next ready feature: `db-schema`
- Current blocker: none
- Last verified at: 2026-11-27 (`./init.sh` exit 0, 13 PHP files, 20 tests, 0 failures)

## Session Log

### Session 001

- Date: 2026-11-17
- Goal: Run discovery and create the minimal startup harness.
- Completed: discovery docs (`CONTEXT.md`, `docs/build-brief.md`, `docs/domain-model.md`, `docs/technical-discovery.md`, `docs/risks-and-open-questions.md`, `docs/adr/0001-php-backend-runs-agent-loop.md`, `DESIGN.md`) and the harness (`AGENTS.md`, `init.sh`, `PROGRESS.md`, `feature_list.json`).
- Verification run: JSON validation and dependency check of `feature_list.json`; `./init.sh` run.
- Evidence captured: see the verification output in the session that created this log.
- Files or artifacts updated: listed above.
- Known risk or unresolved issue: tool calling on DevExpert inference is unverified, and the stdio subprocess on Windows is unproven. Both have spike features early in the list.
- Next best step: `bootstrap-project-skeleton`, then the two spikes, before building the AI connector.

### Session 002

- Date: 2026-11-17
- Goal: Implement `bootstrap-project-skeleton` (spec `docs/specs/bootstrap-project-skeleton.md`).
- Completed: `public/` web root (placeholder `index.html`, placeholder `api/index.php`, vendored W3.CSS 5.01 + `SOURCE.txt`), `src/bootstrap.php`, `src/Config.php`, `config/config.example.php`, `config/apache.conf.example`, plain PHP test runner (`tests/run.php`, `tests/lib/assert.php`, `tests/lib/dev_server.php`, 3 test files, 14 tests), real `init.sh` gate, `.gitignore`, `.gitattributes` (vendor files `-text`).
- Verification run and evidence:
  - `./init.sh` without `config/config.php`: `Syntax check: 10 files, 0 errors`, `14 tests, 0 failures`, config warning, exit 0. Also exit 0 with `config/config.php` present.
  - `php tests/run.php`: `14 tests, 0 failures`, exit 0. `php tests/run.php serving`: 1 test, exit 0.
  - Failure paths (temporary files, removed): failing test -> `FAIL ... :: deliberate failure: Expected 1, got 2`, runner exit 1 and `./init.sh` exit 1; non-array test file -> exit 1; zero tests (`php tests/run.php no-such-test-xyz`) -> `No tests found.`, exit 1; parse error -> `SYNTAX ERROR: ./src/zz_tmp_parse_error.php`, `Syntax check: 11 files, 1 errors`, exit 1.
  - Serving test (short-lived `php -S`, array-form `proc_open`): `/` 200, `w3.css` 200 with `W3.CSS 5.01`, `POST /api/` 200 `application/json` `{"ok":true,"service":"hotel-maintenance-api"}`. No `php.exe` left afterwards (`tasklist`).
  - Manual curl smoke against `php -S`: same JSON and headers; `/../config/config.example.php` -> 404.
  - Config: `config/config.php` ignored (`.gitignore:2`); only the two example files are addable under `config/` (`config/apache.conf` ignored by `.gitignore:3`).
  - W3.CSS SHA-256 `702af7dd8f2a18c566eead0add596d880f05e1d92b096d1cf07f129288b143c0` (24,743 bytes), unmodified.
  - XAMPP Apache (Scenario 4): done by the orchestrator with the user's approval: `config/apache.conf` created (git-ignored) with `<PROJECT_ROOT>` = `C:/Users/mgarcia/Desktop/mgl/Dev/devexpertai-project`; `httpd-vhosts.conf` backed up and `Include ".../config/apache.conf"` appended; `httpd.exe -t` Syntax OK; Apache restarted 14:08. `GET http://localhost/devexpertai/` 200; `POST /devexpertai/api/` 200 `{"ok":true,"service":"hotel-maintenance-api"}`; existing sites still respond (302). Implementer read-only re-check: same 200s, JSON content type, `w3.css` 200 24,743 bytes.
- Not done: visual browser check of the placeholder page at 360px width (no browser available in this session).
- Docs updated: `ARCHITECTURE.md` (new), `CONSTRAINTS.md` (new), `AGENTS.md`, `docs/technical-discovery.md`, `docs/risks-and-open-questions.md` (test tooling resolved), spec Implementation Notes.
- Known risk or unresolved issue: none new. Note for `api-post-json-router`: clean URLs need `RewriteBase` under the Alias and a router script for `php -S`.
- Next best step: independent validation of `bootstrap-project-skeleton` (feature-validator); after acceptance, `db-schema`.

### Validation: bootstrap-project-skeleton (2026-11-17)

- Verdict: accept (independent feature-validator).
- Re-run: `./init.sh` (no config) → 10 files 0 syntax errors, 14 tests 0 failures, warning, exit 0; `init.sh` is 100755, LF, `set -euo pipefail`.
- Failure paths (temporary files, removed): failing test → FAIL line, runner exit 1, `init.sh` exit 1; non-array file → exit 1; top-level throw → exit 255; zero tests → exit 1; parse error in `db/` and in a filename with spaces → SYNTAX ERROR, `init.sh` exit 1.
- `php -S` (repo dev_server helper): `POST /api/` 200 `application/json` `{"ok":true,"service":"hotel-maintenance-api"}`; `GET /` 200; `/../config` and `/../src` → 404; no php.exe left.
- XAMPP (read-only): `/devexpertai/` 200, identical to `public/index.html`; `POST /devexpertai/api/` 200 JSON; `w3.css` 200 24,743 B; traversal 404; `/assets/` listing 403.
- Config: `config/config.php` ignored (`.gitignore:2`), `apache.conf` ignored (`.gitignore:3`); only the example files are addable; no secrets in the diff.
- W3.CSS SHA-256 `702af7dd...143c0` matches `SOURCE.txt` and a fresh upstream download; vendor `-text` rule justified (`core.autocrlf=true` would break the hash).
- Not done: 360px visual check. Judged non-material for a stock W3.CSS placeholder; do it with the first `ui-*` feature.
- Low notes (non-blocking): runner prints no FAIL line when a test file throws at load time (the gate still fails); free-port race in the dev_server helper.

### Session 003 (design change, no feature)

- Date: 2026-11-27
- Goal: User-approved design change: use Google Material Symbols for icons, hosted locally (exception to "no dependencies beyond W3.CSS").
- Completed: vendored `public/assets/vendor/material-symbols/MaterialSymbolsOutlined.woff2` (static Outlined, FILL 0 / wght 400, matches the Stitch designs; 325,336 B, SHA-256 `c596b364...6312`) + `LICENSE` (Apache 2.0) + `SOURCE.txt`; `public/assets/css/icons.css` (`@font-face`, `font-display: block`, `.material-symbols-outlined`); `tests/bootstrap/icons_test.php` (3 tests: SHA matches SOURCE.txt, relative URL and no Google URLs in icons.css, `php -S` serves css + font).
- Verification: `./init.sh` exit 0, 12 files, 17 tests, 0 failures. Content type `font/woff2` from both `php -S` and XAMPP Apache (`/devexpertai/...` 200, 325,336 B).
- Docs updated: `DESIGN.md` (`icons` tokens, Icons section, Don'ts), `CONSTRAINTS.md` (icon rule), `AGENTS.md`, `ARCHITECTURE.md`, `docs/build-brief.md`, `docs/technical-discovery.md`.
- Not done: no page uses icons yet (the first `ui-*` feature links `assets/css/icons.css`); Stitch PNGs in `docs/design/stitch/` are untracked and not yet referenced from `DESIGN.md`.
- Next best step: unchanged, `db-schema`.

### Session 003b (design: all Stitch screens)

- Date: 2026-11-27
- Goal: Document every Stitch export in `DESIGN.md` and adopt the Stitch visual style.
- User decisions: Stitch palette and Source Sans 3 (hosted locally, second approved font); dashboard keeps the specified content (5 status + 3 priority tiles) with the Stitch tile look, no recent activity list; list gets the Stitch layout and row buttons but no search, filters, sorting or pagination; the floating AI button is on the dashboard and the list.
- Completed: `DESIGN.md` rewritten (Stitch asset table with "use / ignore" per image, teal tokens with AA-safe `primaryStrong`, status/priority tag pairs, Source Sans 3 typography, layout, side menu, dialogs, components, screens 1-6). Vendored `public/assets/vendor/source-sans-3/SourceSans3-latin.woff2` (28,872 B, SHA-256 `19143dca...c7d5`) + `OFL.txt` + `SOURCE.txt`; `public/assets/css/fonts.css`; `tests/bootstrap/fonts_test.php` (3 tests). UI feature entries in `feature_list.json` updated (behaviour, notes; `ui-work-order-list` verification no longer says "FAB only on this screen"). `docs/build-brief.md` (MVP step 2, Stitch features out of the PoC), `AGENTS.md`, `CONSTRAINTS.md`, `ARCHITECTURE.md`, `docs/technical-discovery.md`.
- Verification: `./init.sh` exit 0, 13 files, 20 tests, 0 failures. Contrast ratios computed for every token pairing (all text pairings >= 4.5:1; white on `#009688` is 3.67:1, so it is limited to large bold text).
- Side menu has only Dashboard and Partes de trabajo, as in Stitch; work orders are created from the list's "Nuevo parte" and the AI button (confirmed by the user). Hotel name: "Demo Hotel" (user decision; replaces the Stitch placeholder "Grand Hotel").
- Next best step: unchanged, `db-schema`.
