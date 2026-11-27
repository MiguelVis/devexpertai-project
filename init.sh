#!/usr/bin/env bash
# Standard startup and verification gate. Run from Git Bash on Windows: ./init.sh
# Checks only; it never starts a long-running process.
set -euo pipefail

cd "$(dirname "$0")"
echo "Repository: $(pwd)"

# 1. PHP available and >= 8.0
if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: php not on PATH. Add the XAMPP PHP folder (e.g. C:/xampp/php) to PATH." >&2
  exit 1
fi
echo "PHP: $(php -r 'echo PHP_VERSION;')"
if ! php -r 'exit(PHP_VERSION_ID >= 80000 ? 0 : 1);'; then
  echo "ERROR: PHP 8.0 or newer is required." >&2
  exit 1
fi

# 2. Syntax check every project PHP file (tooling folders excluded).
echo "== Syntax check =="
php_files=()
while IFS= read -r file; do
  php_files+=("$file")
done < <(find . \( -path ./.git -o -path ./.agents -o -path ./.claude \) -prune -o -name '*.php' -print | sort)

if [ "${#php_files[@]}" -eq 0 ]; then
  echo "ERROR: no PHP files found to check." >&2
  exit 1
fi

syntax_failures=0
for file in "${php_files[@]}"; do
  if ! output=$(php -l "$file" 2>&1); then
    echo "$output" >&2
    echo "SYNTAX ERROR: $file" >&2
    syntax_failures=$((syntax_failures + 1))
  fi
done
echo "Syntax check: ${#php_files[@]} files, ${syntax_failures} errors"
if [ "$syntax_failures" -ne 0 ]; then
  exit 1
fi

# Database hint, printed before the tests (so it is visible if they fail) and again at the end.
print_db_hint() {
  echo "  - database:    once per worktree, with DB = db.name from config/config.php (must start with hm_):"
  echo "                 /c/xampp/mysql/bin/mysql.exe --defaults-extra-file=config/mysql.cnf -e \"CREATE DATABASE IF NOT EXISTS DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci\""
  echo "                 (the live DB tests apply the schema themselves; to use the app also run:)"
  echo "                 /c/xampp/mysql/bin/mysql.exe --defaults-extra-file=config/mysql.cnf DB < db/schema.sql   (drop-and-create, deletes all rows)"
}

# 3. Local config (warning only; the gate does not need it). With a config whose db.name is a
#    hm_* database, the live DB tests need that database to exist ("Unknown database" otherwise).
if [ ! -f config/config.php ]; then
  echo "WARNING: config/config.php is missing. Copy config/config.example.php to config/config.php and fill in the values."
else
  echo "NOTE: live DB tests need the hm_* database named in config/config.php to exist; they fail with 'Unknown database' until it is created:"
  print_db_hint
fi

# 4. Tests (exit code propagates through set -e).
echo "== Tests =="
php tests/run.php

if [ ! -f config/config.php ]; then
  echo "WARNING: config/config.php is missing. Copy config/config.example.php to config/config.php and fill in the values."
fi

# 5. Manual follow-ups.
echo "== Gate passed =="
echo "Manual follow-ups:"
echo "  - dev server:  php -S 127.0.0.1:8080 -t public   (then open http://127.0.0.1:8080/)"
echo "  - XAMPP:       http://localhost/devexpertai/   (after the Include step in config/apache.conf.example)"
print_db_hint
