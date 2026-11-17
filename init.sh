#!/usr/bin/env bash
# Standard startup and verification path. Run from Git Bash on Windows.
set -euo pipefail

echo "Repository: $(pwd)"
echo "Harness status: pre-bootstrap"
echo "Application stack is not initialized yet (no PHP source, schema or tests)."

if command -v php >/dev/null 2>&1; then
  echo "PHP found: $(php -r 'echo PHP_VERSION;')"
else
  echo "WARNING: php not on PATH. Add the XAMPP PHP folder (e.g. C:/xampp/php) to PATH."
fi

echo "Next step: implement feature 'bootstrap-project-skeleton' from feature_list.json."
echo "Expected future commands (to be made real by the bootstrap feature):"
echo "  - syntax check:  find . -name '*.php' -not -path './.agents/*' -exec php -l {} \\;"
echo "  - tests:         php tests/run.php"
echo "  - database:      mysql -u root < db/schema.sql && mysql -u root < db/seed.sql"
echo "  - app:           served by XAMPP Apache"
