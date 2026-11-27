<?php
// Live database helpers for tests (auto-loaded by tests/run.php).
// Destructive DB tests run only against a disposable database whose name matches hm_<name>
// (for example hm_db_schema). These helpers never issue CREATE/DROP DATABASE or USE, never
// change the session sql_mode, and never print the password.

declare(strict_types=1);

/**
 * True when $name is a disposable per-feature database name: hm_ followed by [a-z0-9_]+.
 */
function test_db_name_is_disposable(string $name): bool
{
    return preg_match('/^hm_[a-z0-9_]+$/D', $name) === 1;
}

/**
 * Returns null when live DB tests may run, otherwise the reason they are skipped.
 */
function test_db_skip_reason(): ?string
{
    $path = APP_ROOT . '/config/config.php';
    if (!is_file($path)) {
        return 'config/config.php is missing';
    }
    try {
        $config = App\Config::load($path);
    } catch (Throwable $e) {
        return 'config/config.php could not be loaded';
    }
    $name = $config['db']['name'] ?? null;
    if (!is_string($name) || !test_db_name_is_disposable($name)) {
        return 'db.name is not a disposable hm_* database';
    }
    return null;
}

/**
 * Connects to the configured disposable database. Throws if the guard does not allow it.
 */
function test_db_connect(): PDO
{
    $reason = test_db_skip_reason();
    if ($reason !== null) {
        throw new RuntimeException("Live DB tests not allowed: {$reason}");
    }
    $db = App\Config::loadDefault()['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        (string) ($db['host'] ?? '127.0.0.1'),
        (int) ($db['port'] ?? 3306),
        (string) $db['name']
    );
    return new PDO($dsn, (string) ($db['user'] ?? ''), (string) ($db['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * Splits a db/*.sql script into statements (without the trailing semicolon).
 * Format rule: full-line "--" comments only, each statement ends with ";" at the end of a line,
 * no ";" inside comments or string literals, no DELIMITER.
 *
 * @return list<string>
 */
function test_db_split_sql(string $sql): array
{
    $statements = [];
    $current = [];
    foreach (preg_split('/\R/', $sql) ?: [] as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $current[] = rtrim($line);
        if (str_ends_with($trimmed, ';')) {
            $statement = trim(substr(implode("\n", $current), 0, -1));
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $current = [];
        }
    }
    $rest = trim(implode("\n", $current));
    if ($rest !== '') {
        $statements[] = $rest;
    }
    return $statements;
}

/**
 * Executes every statement of the SQL file at $path on $pdo, in order.
 */
function test_db_apply_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Could not read SQL file: {$path}");
    }
    foreach (test_db_split_sql($sql) as $statement) {
        $pdo->exec($statement);
    }
}
