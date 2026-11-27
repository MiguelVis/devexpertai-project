<?php
// Connection-free tests for tests/lib/db.php and the db/schema.sql format rules.

declare(strict_types=1);

return [
    'disposable DB name guard accepts hm_ names only' => function (): void {
        assert_true(test_db_name_is_disposable('hm_db_schema'), 'hm_db_schema:');
        assert_true(test_db_name_is_disposable('hm_x1'), 'hm_x1:');
        foreach (['hotel_maintenance', 'HM_x', 'hm_', 'hm_x;drop', 'hm_x ', "hm_x\n", 'xhm_db', 'hm-db', ''] as $name) {
            assert_same(false, test_db_name_is_disposable($name), 'Name ' . var_export($name, true) . ':');
        }
    },

    'SQL splitter drops full-line comments and splits on line-ending semicolons' => function (): void {
        $sql = "-- header comment\n"
            . "SET NAMES utf8mb4;\n"
            . "\n"
            . "  -- indented comment\n"
            . "CREATE TABLE t (\r\n"
            . "    id INT,\r\n"
            . "    name VARCHAR(10) DEFAULT 'a'\r\n"
            . ");\r\n"
            . "DROP TABLE IF EXISTS t;\n"
            . "-- trailing comment\n";
        $statements = test_db_split_sql($sql);
        assert_same(3, count($statements), 'Statement count:');
        assert_same('SET NAMES utf8mb4', $statements[0]);
        assert_same("CREATE TABLE t (\n    id INT,\n    name VARCHAR(10) DEFAULT 'a'\n)", $statements[1]);
        assert_same('DROP TABLE IF EXISTS t', $statements[2]);
        assert_same([], test_db_split_sql("-- only a comment\n\n"));
    },

    'db/schema.sql names no database and follows the splittable format' => function (): void {
        $sql = file_get_contents(APP_ROOT . '/db/schema.sql');
        assert_true(is_string($sql) && $sql !== '', 'schema.sql readable:');
        assert_true(!str_starts_with($sql, "\xEF\xBB\xBF"), 'No UTF-8 BOM:');
        assert_same(1, preg_match('//u', $sql), 'Valid UTF-8:');
        // Checked on the statements (comments removed): the header comment documents the rules.
        $code = implode("\n", test_db_split_sql($sql));
        foreach (['/\bCREATE\s+DATABASE\b/i', '/\bDROP\s+DATABASE\b/i', '/\bSCHEMA\b/i', '/^\s*USE\s/im', '/\bDELIMITER\b/i', '/\bTRIGGER\b/i', '/\bPROCEDURE\b/i', '/\bVIEW\b/i', '/\bEVENT\b/i', '/\bENUM\s*\(/i', '/\bAS\s+BINARY\b/i'] as $pattern) {
            assert_same(0, preg_match($pattern, $code), "Forbidden pattern {$pattern}:");
        }
        assert_same(0, preg_match('/\b(CREATE|DROP)\s+DATABASE\b/i', $sql), 'No CREATE/DROP DATABASE anywhere, comments included:');
        foreach (preg_split('/\R/', $sql) ?: [] as $n => $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '--')) {
                assert_true(!str_contains($trimmed, ';'), 'Semicolon in comment on line ' . ($n + 1) . ':');
            } elseif (str_contains($trimmed, ';')) {
                assert_true(str_ends_with($trimmed, ';') && substr_count($trimmed, ';') === 1, 'Semicolon not at line end on line ' . ($n + 1) . ':');
            }
        }
        $creates = array_values(array_filter(test_db_split_sql($sql), fn (string $s): bool => str_starts_with($s, 'CREATE TABLE')));
        assert_same(12, count($creates), 'CREATE TABLE count:');
        // The only rows the schema writes are the fixed lookup rows of statuses and priorities.
        $inserts = array_values(array_filter(test_db_split_sql($sql), fn (string $s): bool => preg_match('/\bINSERT\b/i', $s) === 1));
        assert_same(2, count($inserts), 'INSERT statement count:');
        assert_true(str_starts_with($inserts[0], 'INSERT INTO statuses ('), 'First INSERT is into statuses:');
        assert_true(str_starts_with($inserts[1], 'INSERT INTO priorities ('), 'Second INSERT is into priorities:');
    },
];
