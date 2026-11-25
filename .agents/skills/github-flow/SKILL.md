---
name: github-flow
description: "Orchestrate multi-feature GitHub delivery on top of feature-flow using git worktrees, independent Claude Code CLI sessions, and gh issues/PRs. Use when the user asks to plan, execute, or publish feature ids in this GitHub repository, to run features in parallel isolated worktrees, to reuse or create issues, branches, worktrees, or PRs for features, or to publish accepted features as PRs. Modes: plan (planificar, read-only), execute (ejecutar), publish (publicar). Never merges."
---

# GitHub Flow

Coordinate one or more `feature_list.json` features across this GitHub repository. This skill owns repository topology: issues, branches, worktrees, sessions, pushes, and PRs. It delegates each feature's planner/implementer/validator loop to `../feature-flow/SKILL.md` inside an isolated worktree session and never implements product code itself.

Environment assumptions (this project): Windows, Git Bash, XAMPP (shared Apache + MariaDB service), PHP 8 on PATH, GitHub remote `origin`.

## Inputs

1. `feature_ids` (required): one or more feature ids from `feature_list.json`.
2. `repository`: resolve the slug from `git remote get-url origin` and confirm it with the user.
3. `base_branch`: default `poc-mvp`. Confirm it if the user did not name one. All worktrees branch from it and all PRs target it.
4. `mode` (required): `plan` | `execute` | `publish` (aliases: planificar | ejecutar | publicar).

If `feature_ids` or `mode` is missing, ask before doing anything.

## Hard Rules

- Plan mode is read-only. It must not mutate git, GitHub, or files: no fetch, push, commit, checkout, stash, rebase, or worktree add; no issue/PR create, comment, or edit; no file writes; no session launch.
- Without acceptance there is no publish. `publish` requires each feature's status to be `accepted` in `feature_list.json` on its feature branch (independent validator `accept`). `passing` is not accepted.
- Without explicit push permission there is no push. Push permission is granted per run by the user; the default is denied. `execute` keeps commits local; `publish` pushes and opens PRs only with that permission.
- Merge is always out of scope. Never merge a PR and never enable auto-merge.
- If a request repeats, reuse the existing issue, worktree, branch, and PR. Never duplicate them. Never delete or rewrite published work: no worktree removal, branch deletion, force push, or issue/PR closing.
- One feature per session, one session per worktree. Never two features in the same session.
- Sessions are headless Claude Code CLI runs started with the worktree as working directory (see Session Command). Never use `--dangerously-skip-permissions` unless the user explicitly asks for it in this run.
- All worktrees branch from one common base SHA fixed at the start of `execute`.
- A blocked feature is reported with its blocking dependency ids. Never silently skip it and never substitute a later feature.
- `feature-flow` owns the role loop and acceptance inside a worktree session; its commit and validation rules still apply there (the accepted commit lands on `feature/<feature-id>`).
- Shared environment resources (MariaDB service, Apache, ports) are never reconfigured by this skill. Isolation comes from per-worktree database names, config files, and ports.

## Preconditions

Checked before `execute` is allowed to mutate anything. `plan` runs the same checks read-only. Every feature to execute must pass all five:

1. **Dependencies accepted:** every id in `depends_on` has status `accepted` in `feature_list.json` on `base_branch`.
2. **Clean base:** the base checkout has no staged, modified, or deleted tracked files (`git status --porcelain` has no tracked entries; untracked files are allowed and reported), no merge/rebase in progress, and `base_branch` resolves.
3. **Tools:** `gh` is on PATH and `gh auth status` shows an account that can manage issues and PRs in the repository; `claude` (Claude Code CLI) is on PATH; `./init.sh` passes on the base checkout. If a tool is missing, report it with its install hint (`winget install GitHub.cli`; Claude Code CLI per its official docs) and stop; do not install anything without the user's approval.
4. **Push permission:** record whether the user explicitly granted it for this run. Without it, work stays local and `publish` cannot push or open PRs.
5. **Independent environments:** one worktree per feature outside the base checkout, prepared as in Worktree Setup, with no shared mutable state between features.

If any precondition fails, report the failing features and stop before mutating anything.

## Deterministic Identities (reuse keys)

- Branch: `feature/<feature-id>`.
- Worktrees root: `../<base-checkout-dirname>-worktrees` (here `../devexpertai-project-worktrees`), outside the base checkout.
- Worktree: `<worktrees-root>/<feature-id>`.
- Session log: `<worktrees-root>/_logs/<feature-id>.json`.
- Database name: `hm_<feature-id with "-" replaced by "_">`, truncated to 64 characters.
- Dev server port: `8100 + <0-based index of the feature in feature_list.json>`.
- Issue title: `[<feature-id>] <feature title from feature_list.json>`.
- PR: head `feature/<feature-id>` into `base_branch`, title `[<feature-id>] <feature title>`.

Reuse lookup before any creation (read-only):

- Issue: `gh issue list --state all --search "<feature-id> in:title"`; reuse the issue whose title starts with `[<feature-id>]`; create only if none exists.
- Worktree and branch: `git worktree list` and `git branch --list "feature/<feature-id>"`; reuse when present; `git worktree add` only if absent.
- PR: `gh pr list --state all --head "feature/<feature-id>" --base <base_branch>`; reuse when present; create only in `publish` and only if absent.

## Worktree Setup (Windows)

Run once per new worktree, idempotently, in `execute`:

1. `git worktree add <worktrees-root>/<feature-id> -b feature/<feature-id> <base_sha>` (or reuse).
2. Skills link: the tracked `.claude/skills` symlink does not resolve on Windows. In the worktree, replace it with a junction and hide the local difference:
   `cmd //c "rmdir .claude\skills"` (ignore if absent), `cmd //c "mklink /J .claude\skills <absolute worktree path>\.agents\skills"`, then `git update-index --skip-worktree .claude/skills`. (`/.claude/skills/` is already excluded through the shared `.git/info/exclude`.)
3. Local config: if `config/config.php` is absent, copy `config/config.example.php` from the worktree and copy real secrets (LLM keys, MCP key, service-account password) from the base checkout's `config/config.php` when it exists; set the database name to the feature's identity. Never commit it (it is git-ignored).
4. Record the feature's database name and dev-server port in the session prompt so tests and manual smokes in that worktree use them. Worktrees are not served by XAMPP Apache; they use `php -S 127.0.0.1:<port> -t public`.
5. Run `./init.sh` in the worktree; if it fails, report and do not launch the session.

## Session Command

From Git Bash, one background process per feature:

```bash
cd "<worktrees-root>/<feature-id>" && claude -p "Use feature-flow for <feature-id> only, until accepted. Base branch: <base_branch>. Database: <db-name>. Dev server port: <port>. Edit only this feature's entry in feature_list.json and append only this feature's section to PROGRESS.md. Do not push." --settings .claude/settings.headless.json --permission-mode acceptEdits --output-format json > "<worktrees-root>/_logs/<feature-id>.json"
```

- `.claude/settings.headless.json` is the approved allowlist for headless runs: php, `./init.sh`, the XAMPP mysql client, local read/commit git commands; it denies push, fetch, rebase, merge, reset, checkout, worktree, `gh`, curl, package managers, and edits to `config/config.php`, `.claude/` and `.agents/`. It is used only by these sessions, so interactive sessions keep their normal permissions.
- `--permission-mode acceptEdits` lets the session edit files in its worktree without prompts; anything not allowed above is denied rather than prompted.
- The sessions use the project subagents `planner`, `implementer` and `validator` from `.claude/agents/`.
- If a session stops on a permission denial, report it; never re-run it with broader permissions on your own.

## Shared Harness Files

`feature_list.json` and `PROGRESS.md` are edited by every feature branch. To keep each PR self-contained:

- Inside a session, the feature changes only its own entry in `feature_list.json` and appends only its own section to `PROGRESS.md` (sessions are told this in the prompt).
- Before the first push in `publish`, bring the branch up to date: `git fetch origin <base_branch>` then `git rebase origin/<base_branch>` in the worktree. Resolve conflicts mechanically: in `feature_list.json` take the base version and re-apply only this feature's entry; in `PROGRESS.md` keep the base content and re-append this feature's sections. Any conflict outside these two files stops publishing for that feature and is reported.
- After the rebase, re-run `./init.sh` in the worktree. If it fails, do not publish; report and route the feature back through `feature-flow`.
- If the branch was already pushed (an existing PR), never rebase it again: merge `origin/<base_branch>` into the feature branch instead, so no force push is needed.

## Mode: plan (planificar)

Read-only. Produce the plan report only.

1. Resolve inputs; read `feature_list.json`, `PROGRESS.md`, git state (`git status --porcelain`, `git rev-parse <base_branch>`, `git worktree list`, `git branch --list`) and tool/gh state (`command -v gh claude`, `gh auth status`, issue and PR lookups when `gh` is available). Never `git fetch`.
2. Evaluate the five preconditions per feature. For blocked features, report the missing `accepted` dependency ids. Report missing tools as a run-level blocker.
3. Planned base SHA = current `git rev-parse <base_branch>`.
4. For each ready feature, resolve reuse targets (issue, worktree, branch, PR), database name, port, and the planned session command.
5. Emit the output report with status `planned` and `pr: none`. Nothing is created.

## Mode: execute (ejecutar)

1. Run the precondition gate. Split the request into ready and blocked features and report both lists; continue with ready features only.
2. Fix the common base SHA: `git fetch origin <base_branch>` then `base_sha=$(git rev-parse origin/<base_branch>)`. Every worktree branches from this exact SHA.
3. Record whether the user granted push permission. Without it, do not push anything.
4. For each ready feature, independently:
   1. Reuse or create the issue (lookup first).
   2. Reuse or create and prepare the worktree (Worktree Setup).
   3. Launch exactly one session (Session Command). Different features use different worktrees and sessions and may run concurrently.
   4. Collect the `feature-flow` outcome from the session log and the worktree (validator `accept` / `revise` / `block`, `feature_list.json` status) and the local commit created on accept.
   5. Keep the issue body updated with status and evidence (GitHub writes are allowed here; push is still gated).
5. Emit the output report.

## Mode: publish (publicar)

1. Acceptance gate: every feature must be `accepted` in `feature_list.json` on its feature branch. If any is not, refuse that feature: without acceptance there is no publish. `passing` does not qualify.
2. Push gate: if push permission was not explicitly granted, refuse: without permission there is no push, and therefore no PR.
3. Update the branch per Shared Harness Files and re-run `./init.sh`.
4. Push `feature/<feature-id>` and reuse or create the PR into `base_branch` with the acceptance evidence in the body and `Closes #<issue>`. End the PR body with the attribution line required by the session, if any.
5. Never merge. Report the PR URL per published feature.

## Output Report

Header: mode, repository, base branch, base SHA, push permission (granted/denied), tools (gh/claude present, gh account), reused vs created artifacts.

Per feature, one row/block with exactly these fields:

- `feature`: feature id.
- `session`: the session command, or `none` in plan mode.
- `branch`: `feature/<id> @ <base_sha>`.
- `env`: database name and dev-server port.
- `status`: gate verdict or feature status: `blocked`, `planned`, `in_progress`, `passing`, `accepted`, `published`, `refused`.
- `commit`: hash and subject, or `none`.
- `evidence`: verification or acceptance summary.
- `pr`: PR URL when a PR exists or was published, otherwise `none`.
