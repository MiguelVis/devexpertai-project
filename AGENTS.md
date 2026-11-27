# Project Agent Instructions

This repository contains a proof-of-concept web app for a hotel's maintenance staff. Users describe a problem in free text, an LLM grounded by the project's own stdio MCP server prefills a work order, and the user reviews and saves it. Stack: PHP 8 (no frameworks), MySQL/MariaDB, HTML/CSS/W3.CSS, plain JavaScript, running locally on Windows with XAMPP.

## Read First

- `CONTEXT.md`: domain language (work order, typology, location, AI Prefill...).
- `docs/build-brief.md`: goals, non-goals, MVP slice, success criteria.
- `docs/domain-model.md`: entities, relationships, status lifecycle.
- `docs/risks-and-open-questions.md`: risks and unresolved questions.

Read these only when relevant:

- `docs/technical-discovery.md`: when touching the API, auth, the AI connector, the MCP server, configuration, testing or logging.
- `docs/adr/`: before changing how the AI loop, the MCP or LLM providers work.
- `DESIGN.md`: when touching any UI.
- `ARCHITECTURE.md`: directory layout, web root, autoloading, serving modes, config loading.
- `CONSTRAINTS.md`: MUST / MUST NOT rules every feature has to respect.

## Startup Workflow

Before writing code:

1. Confirm the working directory with `pwd`.
2. Read `PROGRESS.md` for the current verified state and the next step.
3. Read `feature_list.json` and pick the first unfinished feature, in list order, whose `depends_on` features are all `accepted` (`passing` means self-verified only; acceptance comes from the independent validator).
4. Run `./init.sh` (Git Bash on Windows). It is the real gate: PHP syntax check of all project files plus `php tests/run.php`; it exits non-zero on any failure and starts no long-running process.
5. If baseline verification fails, fix the baseline before adding new feature work.
6. If `config/config.php` is missing (the gate warns), copy `config/config.example.php` to it and fill in local values. It is git-ignored.
7. To see the app: `php -S 127.0.0.1:8080 -t public` and open `http://127.0.0.1:8080/`. XAMPP Apache serves it at `http://localhost/devexpertai/` once the snippet in `config/apache.conf.example` is included (manual step).

## Working Rules

- Work on one feature at a time; at most one feature is `in_progress`.
- No dependencies beyond W3.CSS and two fonts, Material Symbols (icons) and Source Sans 3 (text), all vendored and served locally: no Composer packages, npm packages, frameworks, JS libraries or CDN assets. Ask before making any other exception.
- All API endpoints are POST and return JSON. Every endpoint except `/login` requires a valid token.
- Never trust IDs returned by the LLM; the backend validates them against the database.
- The MCP server is read-only.
- Secrets (DB credentials, LLM keys, MCP key, service account password) live only in the git-ignored config file, never in code, logs or commits.
- Use the canonical terms from `CONTEXT.md` in code, API and UI copy (UI copy in Spanish).
- Do not mark a feature complete just because code was added.
- Keep changes inside the selected feature's scope unless a blocker requires a narrow supporting fix.
- Do not silently change verification rules during implementation.
- Update durable repo artifacts instead of relying on chat summaries.

## Required Artifacts

- `feature_list.json`: source of truth for feature state.
- `PROGRESS.md`: current verified state and a lightweight session log.
- `init.sh`: the standard startup and verification path.

## Definition Of Done

A feature is done only when all of the following are true:

- the target behavior is implemented,
- the required verification actually ran,
- evidence is recorded in `feature_list.json` or `PROGRESS.md`,
- the repository can still be restarted from the standard startup path,
- the relevant docs are updated if product behavior, domain rules, the API or verification changed.

## End Of Session

Before ending a session:

1. Update `PROGRESS.md`.
2. Update `feature_list.json`.
3. Record unresolved risks or blockers.
4. Leave the repo clean enough for the next session to run `./init.sh` immediately.
