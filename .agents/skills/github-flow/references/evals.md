# github-flow evals

Contract evals for `../SKILL.md`. Every run uses mode `plan` (planificar) and must finish with zero side effects: no git mutations, no GitHub mutations, no file writes, no session launch. Publish gating in Eval 4 is exercised through the same read-only gate evaluation.

Common inputs for all evals:

- `repository`: this checkout; resolve the slug from `git remote get-url origin`.
- `base_branch`: `poc-mvp` (where `bootstrap-project-skeleton` is `accepted`).
- Feature source: `feature_list.json` as it stands at eval time. The states below are those after `bootstrap-project-skeleton` was accepted; if they have changed, pick features that recreate the same situation and note the substitution.
- Allowed reads only: `feature_list.json`, `PROGRESS.md`, `git status --porcelain`, `git rev-parse <base_branch>`, `git worktree list`, `git branch --list`, `command -v gh claude`, `gh auth status`, `gh issue list`, `gh pr list`.
- If `gh` or `claude` is missing, the report must state it as a run-level blocker; the feature-level verdicts below must still be produced.

Record per eval: verdict `pass`/`fail` and the observed report lines that prove it.

## Eval 1: blocked feature with a missing accepted dependency

- Input: mode `plan`, `feature_ids = ["api-login"]`.
- State: `api-login` depends_on `["api-post-json-router", "db-seed-data"]`; both are `not_started`.
- Expected: blocked, missing accepted dependencies `api-post-json-router` and `db-seed-data`. No issue, worktree, branch, session, or PR is planned. Zero side effects.
- Pass criteria: the report names `api-login` and both dependency ids and states they are not `accepted`; planned artifact count is zero.

## Eval 2: two ready features, one base SHA, two worktrees

- Input: mode `plan`, `feature_ids = ["db-schema", "api-post-json-router"]`.
- State: both depend_on `["bootstrap-project-skeleton"]` (`accepted`).
- Expected: both features ready; one common base SHA shared by both; two independent worktrees (`../devexpertai-project-worktrees/db-schema` and `.../api-post-json-router`), one planned `claude -p` session per worktree, distinct database names (`hm_db_schema`, `hm_api_post_json_router`) and distinct ports; never two features in the same session. Per feature the report includes session, branch, env, status, commit, evidence, and pr.
- Pass criteria: both features report status `planned`; the base SHA is identical for both; worktree paths, database names, and ports all differ; no session is shared; zero side effects.

## Eval 3: a repeated request is idempotent

- Input: the exact same request as Eval 2, issued a second time.
- Expected: same plan (same base SHA, worktree paths, branches, database names, ports, issue and PR reuse targets, and session commands) and nothing new to create: the delta against the first run is empty. No duplicate artifacts and no deletions are planned.
- Pass criteria: run 2 identity set equals run 1 identity set; run 2 plans zero additional artifacts relative to run 1; zero side effects.

## Eval 4: publish without acceptance is refused

- Input: mode `publish`, `feature_ids = ["db-schema"]` (status `not_started`), evaluated without effects.
- Expected: refused, because without acceptance there is no publish. No rebase, push, PR, issue, worktree, branch, or file changes.
- Pass criteria: verdict `refused` citing that the status is not `accepted`; zero side effects.

## Running

Run each eval through the skill in `plan` mode against the live read-only state listed above. Never run these evals in `execute` or `publish` mode for real: Eval 4 checks the refusal verdict only.
