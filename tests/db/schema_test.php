<?php
// Live tests for db/schema.sql. They run only against a disposable hm_* database named in
// config/config.php (db.name); otherwise the file prints one SKIP line and returns no tests.
// Every test re-applies db/schema.sql first, and the last one leaves the schema with only the
// fixed statuses and priorities rows.

declare(strict_types=1);

$skipReason = test_db_skip_reason();
if ($skipReason !== null) {
    echo "SKIP tests/db/schema_test.php: {$skipReason}\n";
    return [];
}

$schemaPath = APP_ROOT . '/db/schema.sql';

$expectedTables = [
    'ai_prefill_log',
    'auth_tokens',
    'level1_typologies',
    'level2_typologies',
    'level3_typologies',
    'location_types',
    'locations',
    'priorities',
    'statuses',
    'users',
    'work_order_status_history',
    'work_orders',
];

// Fixed lookup rows inserted by db/schema.sql (ids are a contract for the seed and the API).
$expectedStatuses = [
    ['id' => 1, 'name' => 'Pendiente', 'is_closed' => 0, 'sort_order' => 1],
    ['id' => 2, 'name' => 'Asignado', 'is_closed' => 0, 'sort_order' => 2],
    ['id' => 3, 'name' => 'En resolución', 'is_closed' => 0, 'sort_order' => 3],
    ['id' => 4, 'name' => 'Resuelto', 'is_closed' => 1, 'sort_order' => 4],
    ['id' => 5, 'name' => 'Cancelado', 'is_closed' => 1, 'sort_order' => 5],
];
$expectedPriorities = [
    ['id' => 1, 'name' => 'Baja', 'sort_order' => 1],
    ['id' => 2, 'name' => 'Media', 'sort_order' => 2],
    ['id' => 3, 'name' => 'Alta', 'sort_order' => 3],
];
$lookupCounts = ['statuses' => 5, 'priorities' => 3];

$fresh = static function () use ($schemaPath): PDO {
    $pdo = test_db_connect();
    test_db_apply_sql_file($pdo, $schemaPath);
    return $pdo;
};

// Asserts the lookup tables hold exactly their fixed rows and every other table is empty.
$assertBaseline = static function (PDO $pdo, string $label) use ($expectedTables, $lookupCounts): void {
    foreach ($expectedTables as $table) {
        $expected = $lookupCounts[$table] ?? 0;
        assert_same($expected, (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(), "{$label} {$table} row count:");
    }
};

// Runs $fn and asserts it fails with the given MariaDB error code (errorInfo[1]).
$expectDbError = static function (int $code, callable $fn, string $label): void {
    $e = assert_throws($fn, PDOException::class, $label);
    $actual = $e instanceof PDOException && is_array($e->errorInfo) ? (int) ($e->errorInfo[1] ?? 0) : 0;
    assert_same($code, $actual, $label . ' MariaDB error code (' . $e->getMessage() . '):');
};

// Inserts one location (with type), one L1/L2/L3 typology chain and one user. Returns their ids.
$seedMinimal = static function (PDO $pdo): array {
    $pdo->exec("INSERT INTO location_types (name) VALUES ('Habitación')");
    $typeId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO locations (name, location_type_id) VALUES ('Habitación 101', {$typeId})");
    $locationId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO level1_typologies (name) VALUES ('Fontanería')");
    $l1 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO level2_typologies (level1_typology_id, name) VALUES ({$l1}, 'Baño')");
    $l2 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO level3_typologies (level2_typology_id, name, default_priority_id) VALUES ({$l2}, 'Fuga en el lavabo', 2)");
    $l3 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO users (username, password_hash, first_name) VALUES ('tecnico1', 'x', 'Ana')");
    $userId = (int) $pdo->lastInsertId();
    return ['location' => $locationId, 'l1' => $l1, 'l2' => $l2, 'l3' => $l3, 'user' => $userId];
};

// status_id defaults to 1 (Pendiente) and priority_id to 2 (Media); an explicit null is kept.
$insertWorkOrder = static function (PDO $pdo, array $v): void {
    $stmt = $pdo->prepare(
        'INSERT INTO work_orders (creator_user_id, assignee_user_id, status_id, location_id, level3_typology_id, priority_id, description)'
        . ' VALUES (:creator, :assignee, :status_id, :location, :l3, :priority_id, :description)'
    );
    $stmt->execute([
        'creator' => $v['creator'],
        'assignee' => $v['assignee'] ?? null,
        'status_id' => array_key_exists('status_id', $v) ? $v['status_id'] : 1,
        'location' => $v['location'],
        'l3' => $v['l3'],
        'priority_id' => array_key_exists('priority_id', $v) ? $v['priority_id'] : 2,
        'description' => $v['description'] ?? 'Gotea el grifo',
    ]);
};

return [
    'schema applies twice and creates exactly the 12 tables with only the lookup rows' => function () use ($fresh, $schemaPath, $expectedTables, $assertBaseline): void {
        $pdo = $fresh();
        test_db_apply_sql_file($pdo, $schemaPath);
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        sort($tables);
        assert_same($expectedTables, $tables);
        $rows = $pdo->query(
            'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        )->fetchAll();
        assert_same(12, count($rows), 'information_schema table count:');
        foreach ($rows as $row) {
            assert_same('InnoDB', $row['ENGINE'], "{$row['TABLE_NAME']} engine:");
            assert_same('utf8mb4_unicode_ci', $row['TABLE_COLLATION'], "{$row['TABLE_NAME']} collation:");
        }
        $assertBaseline($pdo, 'After two applies:');
    },

    'foreign keys, removed text columns and the remaining CHECK constraints' => function () use ($fresh): void {
        $pdo = $fresh();
        $rows = $pdo->query(
            'SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,'
            . ' k.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE'
            . ' FROM information_schema.KEY_COLUMN_USAGE k'
            . ' JOIN information_schema.REFERENTIAL_CONSTRAINTS r'
            . '   ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME'
            . ' WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL'
        )->fetchAll();
        $actual = [];
        foreach ($rows as $row) {
            $key = "{$row['TABLE_NAME']}.{$row['COLUMN_NAME']}";
            $actual[$key] = "{$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']} {$row['DELETE_RULE']}/{$row['UPDATE_RULE']} {$row['CONSTRAINT_NAME']}";
        }
        ksort($actual);
        $expected = [
            'ai_prefill_log.user_id' => 'users.id RESTRICT/RESTRICT fk_ai_prefill_log_user_id',
            'auth_tokens.user_id' => 'users.id CASCADE/RESTRICT fk_auth_tokens_user_id',
            'level2_typologies.level1_typology_id' => 'level1_typologies.id RESTRICT/RESTRICT fk_level2_typologies_level1_typology_id',
            'level3_typologies.default_priority_id' => 'priorities.id RESTRICT/RESTRICT fk_level3_typologies_default_priority_id',
            'level3_typologies.level2_typology_id' => 'level2_typologies.id RESTRICT/RESTRICT fk_level3_typologies_level2_typology_id',
            'locations.location_type_id' => 'location_types.id RESTRICT/RESTRICT fk_locations_location_type_id',
            'work_order_status_history.changed_by_user_id' => 'users.id RESTRICT/RESTRICT fk_work_order_status_history_changed_by_user_id',
            'work_order_status_history.status_id' => 'statuses.id RESTRICT/RESTRICT fk_work_order_status_history_status_id',
            'work_order_status_history.work_order_id' => 'work_orders.id CASCADE/RESTRICT fk_work_order_status_history_work_order_id',
            'work_orders.assignee_user_id' => 'users.id RESTRICT/RESTRICT fk_work_orders_assignee_user_id',
            'work_orders.creator_user_id' => 'users.id RESTRICT/RESTRICT fk_work_orders_creator_user_id',
            'work_orders.level3_typology_id' => 'level3_typologies.id RESTRICT/RESTRICT fk_work_orders_level3_typology_id',
            'work_orders.location_id' => 'locations.id RESTRICT/RESTRICT fk_work_orders_location_id',
            'work_orders.priority_id' => 'priorities.id RESTRICT/RESTRICT fk_work_orders_priority_id',
            'work_orders.status_id' => 'statuses.id RESTRICT/RESTRICT fk_work_orders_status_id',
        ];
        assert_same($expected, $actual);

        $columnsOf = static function (string $table) use ($pdo): array {
            $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        };
        $workOrderColumns = $columnsOf('work_orders');
        foreach (['level1_typology_id', 'level2_typology_id', 'status', 'priority'] as $column) {
            assert_true(!in_array($column, $workOrderColumns, true), "work_orders has no {$column} column:");
        }
        assert_true(!in_array('status', $columnsOf('work_order_status_history'), true), 'work_order_status_history has no status column:');
        assert_true(!in_array('default_priority', $columnsOf('level3_typologies'), true), 'level3_typologies has no default_priority column:');

        $checks = $pdo->query(
            'SELECT CONSTRAINT_NAME FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() ORDER BY CONSTRAINT_NAME'
        )->fetchAll(PDO::FETCH_COLUMN);
        assert_same([
            'chk_ai_prefill_log_prefill_notes',
            'chk_ai_prefill_log_tool_calls',
            'chk_ai_prefill_log_validated_result',
            'chk_statuses_is_closed',
            'chk_users_is_service_account',
        ], $checks, 'Only these CHECK constraints remain:');
    },

    'typology tree is enforced (1452 on unknown parent, 1451 on deleting a parent with children)' => function () use ($fresh, $expectDbError): void {
        $pdo = $fresh();
        $expectDbError(1452, fn () => $pdo->exec("INSERT INTO level2_typologies (level1_typology_id, name) VALUES (999, 'x')"), 'L2 with unknown L1:');
        $expectDbError(1452, fn () => $pdo->exec("INSERT INTO level3_typologies (level2_typology_id, name, default_priority_id) VALUES (999, 'x', 1)"), 'L3 with unknown L2:');
        $pdo->exec("INSERT INTO level1_typologies (name) VALUES ('Electricidad')");
        $l1 = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO level2_typologies (level1_typology_id, name) VALUES ({$l1}, 'Iluminación')");
        $l2 = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO level3_typologies (level2_typology_id, name, default_priority_id) VALUES ({$l2}, 'Bombilla fundida', 1)");
        $expectDbError(1451, fn () => $pdo->exec("DELETE FROM level1_typologies WHERE id = {$l1}"), 'Delete L1 with children:');
        $expectDbError(1451, fn () => $pdo->exec("DELETE FROM level2_typologies WHERE id = {$l2}"), 'Delete L2 with children:');
        $expectDbError(1062, fn () => $pdo->exec("INSERT INTO level2_typologies (level1_typology_id, name) VALUES ({$l1}, 'Iluminación')"), 'Duplicate L2 name under the same L1:');
    },

    'work order references are enforced (1452) and a valid unassigned work order saves' => function () use ($fresh, $expectDbError, $seedMinimal, $insertWorkOrder): void {
        $pdo = $fresh();
        $ids = $seedMinimal($pdo);
        $valid = ['creator' => $ids['user'], 'location' => $ids['location'], 'l3' => $ids['l3']];
        $expectDbError(1452, fn () => $insertWorkOrder($pdo, ['location' => 999] + $valid), 'Unknown location:');
        $expectDbError(1452, fn () => $insertWorkOrder($pdo, ['l3' => 999] + $valid), 'Unknown L3 typology:');
        $expectDbError(1452, fn () => $insertWorkOrder($pdo, ['creator' => 999] + $valid), 'Unknown creator:');
        $expectDbError(1452, fn () => $insertWorkOrder($pdo, ['assignee' => 999] + $valid), 'Unknown assignee:');
        $expectDbError(1452, fn () => $pdo->exec("INSERT INTO locations (name, location_type_id) VALUES ('Sin tipo', 999)"), 'Location with unknown type:');
        $insertWorkOrder($pdo, $valid);
        $row = $pdo->query('SELECT * FROM work_orders')->fetch();
        assert_same(null, $row['assignee_user_id'], 'Assignee NULL:');
        assert_same(1, (int) $row['status_id'], 'status_id:');
        assert_same(2, (int) $row['priority_id'], 'priority_id:');
        assert_true(is_string($row['created_at']) && $row['created_at'] !== '', 'created_at defaulted:');
        $expectDbError(1451, fn () => $pdo->exec("DELETE FROM locations WHERE id = {$ids['location']}"), 'Delete referenced location:');
        $expectDbError(1451, fn () => $pdo->exec("DELETE FROM users WHERE id = {$ids['user']}"), 'Delete referenced creator:');
    },

    'statuses and priorities hold exactly the fixed byte-exact rows and reject look-alikes (1062)' => function () use ($fresh, $expectDbError, $expectedStatuses, $expectedPriorities): void {
        $pdo = $fresh();
        $statuses = $pdo->query('SELECT id, name, HEX(name) AS hex, is_closed, sort_order FROM statuses ORDER BY id')->fetchAll();
        assert_same(count($expectedStatuses), count($statuses), 'statuses row count:');
        foreach ($expectedStatuses as $i => $expected) {
            $row = $statuses[$i];
            $label = "statuses row {$expected['id']}:";
            assert_same($expected['id'], (int) $row['id'], $label . ' id:');
            assert_same($expected['name'], $row['name'], $label . ' name:');
            assert_same(strtoupper(bin2hex($expected['name'])), $row['hex'], $label . ' name bytes:');
            assert_same($expected['is_closed'], (int) $row['is_closed'], $label . ' is_closed:');
            assert_same($expected['sort_order'], (int) $row['sort_order'], $label . ' sort_order:');
        }
        assert_same('456E207265736F6C756369C3B36E', $statuses[2]['hex'], 'En resolución is stored as UTF-8:');
        $priorities = $pdo->query('SELECT id, name, HEX(name) AS hex, sort_order FROM priorities ORDER BY id')->fetchAll();
        assert_same(count($expectedPriorities), count($priorities), 'priorities row count:');
        foreach ($expectedPriorities as $i => $expected) {
            $row = $priorities[$i];
            $label = "priorities row {$expected['id']}:";
            assert_same($expected['id'], (int) $row['id'], $label . ' id:');
            assert_same($expected['name'], $row['name'], $label . ' name:');
            assert_same(strtoupper(bin2hex($expected['name'])), $row['hex'], $label . ' name bytes:');
            assert_same($expected['sort_order'], (int) $row['sort_order'], $label . ' sort_order:');
        }

        $collations = $pdo->query(
            "SELECT TABLE_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()"
            . " AND COLUMN_NAME = 'name' AND TABLE_NAME IN ('statuses', 'priorities') ORDER BY TABLE_NAME"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        assert_same(['priorities' => 'utf8mb4_unicode_ci', 'statuses' => 'utf8mb4_unicode_ci'], $collations, 'Lookup name collation:');

        // Failing probes only: these writes never succeed, and the schema is re-applied by every test.
        $insertStatus = $pdo->prepare('INSERT INTO statuses (id, name, is_closed, sort_order) VALUES (?, ?, ?, ?)');
        $next = 10;
        foreach (['pendiente', 'PENDIENTE', 'En resolucion', 'En resolución ', 'Pendiente '] as $name) {
            $expectDbError(1062, fn () => $insertStatus->execute([$next, $name, 0, $next]), "Status look-alike '{$name}':");
            $next++;
        }
        $expectDbError(1062, fn () => $insertStatus->execute([1, 'Otro', 0, 50]), 'Duplicate status id:');
        $expectDbError(1062, fn () => $insertStatus->execute([50, 'Otro', 0, 1]), 'Duplicate status sort_order:');
        $expectDbError(4025, fn () => $insertStatus->execute([51, 'Otro', 2, 51]), 'Status is_closed = 2:');

        $insertPriority = $pdo->prepare('INSERT INTO priorities (id, name, sort_order) VALUES (?, ?, ?)');
        foreach (['alta', 'Alta ', 'MEDIA'] as $name) {
            $expectDbError(1062, fn () => $insertPriority->execute([$next, $name, $next]), "Priority look-alike '{$name}':");
            $next++;
        }
        $expectDbError(1062, fn () => $insertPriority->execute([1, 'Otra', 50]), 'Duplicate priority id:');
        $expectDbError(1062, fn () => $insertPriority->execute([50, 'Otra', 1]), 'Duplicate priority sort_order:');

        assert_same(5, (int) $pdo->query('SELECT COUNT(*) FROM statuses')->fetchColumn(), 'statuses unchanged:');
        assert_same(3, (int) $pdo->query('SELECT COUNT(*) FROM priorities')->fetchColumn(), 'priorities unchanged:');
    },

    'status and priority foreign keys are enforced (1452, 1048, 1451) under the non-strict sql_mode' => function () use ($fresh, $expectDbError, $seedMinimal, $insertWorkOrder): void {
        $pdo = $fresh();
        $mode = (string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        assert_true(!str_contains($mode, 'STRICT_'), "Proven under the non-strict server default sql_mode ({$mode}):");
        $ids = $seedMinimal($pdo);
        $valid = ['creator' => $ids['user'], 'location' => $ids['location'], 'l3' => $ids['l3']];

        $insertWorkOrder($pdo, ['status_id' => 3, 'priority_id' => 3, 'assignee' => $ids['user']] + $valid);
        $workOrderId = (int) $pdo->lastInsertId();
        $row = $pdo->query(
            'SELECT s.name AS status, p.name AS priority, HEX(s.name) AS hex FROM work_orders w'
            . ' JOIN statuses s ON s.id = w.status_id JOIN priorities p ON p.id = w.priority_id'
            . " WHERE w.id = {$workOrderId}"
        )->fetch();
        assert_same('En resolución', $row['status']);
        assert_same('Alta', $row['priority']);
        assert_same(strtoupper(bin2hex('En resolución')), $row['hex'], 'Joined status bytes are UTF-8:');

        $history = $pdo->prepare('INSERT INTO work_order_status_history (work_order_id, status_id, changed_by_user_id) VALUES (?, ?, ?)');
        $history->execute([$workOrderId, 3, $ids['user']]);
        assert_same('En resolución', $pdo->query('SELECT s.name FROM work_order_status_history h JOIN statuses s ON s.id = h.status_id')->fetchColumn());

        foreach ([1, 2, 3, 4, 5] as $statusId) {
            $insertWorkOrder($pdo, ['status_id' => $statusId] + $valid);
            $history->execute([$workOrderId, $statusId, $ids['user']]);
        }
        foreach ([1, 2, 3] as $priorityId) {
            $insertWorkOrder($pdo, ['priority_id' => $priorityId] + $valid);
            $pdo->exec("INSERT INTO level3_typologies (level2_typology_id, name, default_priority_id) VALUES ({$ids['l2']}, 'Prioridad {$priorityId}', {$priorityId})");
        }

        foreach ([0, 6, 99] as $bad) {
            $expectDbError(1452, fn () => $insertWorkOrder($pdo, ['status_id' => $bad] + $valid), "Work order status_id {$bad}:");
            $expectDbError(1452, fn () => $history->execute([$workOrderId, $bad, $ids['user']]), "History status_id {$bad}:");
            $expectDbError(1452, fn () => $pdo->exec("UPDATE work_orders SET status_id = {$bad} WHERE id = {$workOrderId}"), "Update work order status_id {$bad}:");
            $expectDbError(1452, fn () => $pdo->exec("UPDATE work_order_status_history SET status_id = {$bad} WHERE work_order_id = {$workOrderId}"), "Update history status_id {$bad}:");
        }
        foreach ([0, 4, 99] as $bad) {
            $expectDbError(1452, fn () => $insertWorkOrder($pdo, ['priority_id' => $bad] + $valid), "Work order priority_id {$bad}:");
            $expectDbError(1452, fn () => $pdo->exec("INSERT INTO level3_typologies (level2_typology_id, name, default_priority_id) VALUES ({$ids['l2']}, 'Otra', {$bad})"), "L3 default_priority_id {$bad}:");
            $expectDbError(1452, fn () => $pdo->exec("UPDATE work_orders SET priority_id = {$bad} WHERE id = {$workOrderId}"), "Update work order priority_id {$bad}:");
            $expectDbError(1452, fn () => $pdo->exec("UPDATE level3_typologies SET default_priority_id = {$bad} WHERE id = {$ids['l3']}"), "Update L3 default_priority_id {$bad}:");
        }

        $expectDbError(1048, fn () => $insertWorkOrder($pdo, ['status_id' => null] + $valid), 'Work order status_id NULL:');
        $expectDbError(1048, fn () => $insertWorkOrder($pdo, ['priority_id' => null] + $valid), 'Work order priority_id NULL:');
        $expectDbError(1048, fn () => $history->execute([$workOrderId, null, $ids['user']]), 'History status_id NULL:');
        $expectDbError(1048, fn () => $pdo->exec("INSERT INTO level3_typologies (level2_typology_id, name, default_priority_id) VALUES ({$ids['l2']}, 'Sin prioridad', NULL)"), 'L3 default_priority_id NULL:');

        // Every status 1-5 and priority 1-3 is now referenced: deleting or re-keying them fails.
        foreach ([1, 2, 3, 4, 5] as $statusId) {
            $expectDbError(1451, fn () => $pdo->exec("DELETE FROM statuses WHERE id = {$statusId}"), "Delete referenced status {$statusId}:");
        }
        $expectDbError(1451, fn () => $pdo->exec('UPDATE statuses SET id = 50 WHERE id = 3'), 'Change id of referenced status:');
        foreach ([1, 2, 3] as $priorityId) {
            $expectDbError(1451, fn () => $pdo->exec("DELETE FROM priorities WHERE id = {$priorityId}"), "Delete referenced priority {$priorityId}:");
        }
        $expectDbError(1451, fn () => $pdo->exec('UPDATE priorities SET id = 50 WHERE id = 3'), 'Change id of referenced priority:');

        $pdo->exec("DELETE FROM work_orders WHERE id = {$workOrderId}");
        assert_same(0, (int) $pdo->query("SELECT COUNT(*) FROM work_order_status_history WHERE work_order_id = {$workOrderId}")->fetchColumn(), 'History cascades with its work order:');
    },

    'AI Prefill log accepts JSON or NULL and rejects invalid JSON (4025)' => function () use ($fresh, $expectDbError, $seedMinimal): void {
        $pdo = $fresh();
        $ids = $seedMinimal($pdo);
        $insert = $pdo->prepare(
            'INSERT INTO ai_prefill_log (user_id, provider, model, description, tool_calls, raw_output, validated_result, prefill_notes)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$ids['user'], 'anthropic', 'model-x', 'Gotea el grifo', '[{"name":"search_locations"}]', 'not json at all', '{"location_id":1}', '["nota"]']);
        $insert->execute([$ids['user'], 'devexpert', 'chat', 'Sin datos', null, null, null, null]);
        assert_same(2, (int) $pdo->query('SELECT COUNT(*) FROM ai_prefill_log')->fetchColumn());
        foreach ([4 => 'tool_calls', 6 => 'validated_result', 7 => 'prefill_notes'] as $index => $column) {
            $values = [$ids['user'], 'anthropic', 'model-x', 'x', null, null, null, null];
            $values[$index] = '{bad';
            $expectDbError(4025, fn () => $insert->execute($values), "Invalid JSON in {$column}:");
        }
        $expectDbError(1452, fn () => $insert->execute([999, 'anthropic', 'model-x', 'x', null, null, null, null]), 'Unknown log user:');
    },

    'users defaults, unique username and token cascade; leaves only the lookup rows' => function () use ($fresh, $schemaPath, $expectDbError, $assertBaseline): void {
        $pdo = $fresh();
        $pdo->exec("INSERT INTO users (username, password_hash, first_name) VALUES ('tecnico1', 'x', 'Ana')");
        $userId = (int) $pdo->lastInsertId();
        $row = $pdo->query("SELECT is_service_account, last_names FROM users WHERE id = {$userId}")->fetch();
        assert_same(0, (int) $row['is_service_account'], 'is_service_account default:');
        assert_same('', $row['last_names'], 'last_names default:');
        $expectDbError(1062, fn () => $pdo->exec("INSERT INTO users (username, password_hash, first_name) VALUES ('tecnico1', 'y', 'Otra')"), 'Duplicate username:');
        $expectDbError(4025, fn () => $pdo->exec("INSERT INTO users (username, password_hash, first_name, is_service_account) VALUES ('svc', 'x', 'S', 2)"), 'is_service_account 2:');
        $pdo->exec("INSERT INTO users (username, password_hash, first_name, is_service_account) VALUES ('mcp-service', 'x', 'MCP', 1)");
        $hash = hash('sha256', 'raw-token');
        $pdo->exec("INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES ({$userId}, '{$hash}', NOW() + INTERVAL 8 HOUR)");
        $expectDbError(1062, fn () => $pdo->exec("INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES ({$userId}, '{$hash}', NOW())"), 'Duplicate token hash:');
        $expectDbError(1452, fn () => $pdo->exec("INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (999, 'abc', NOW())"), 'Token for unknown user:');
        $pdo->exec("DELETE FROM users WHERE id = {$userId}");
        assert_same(0, (int) $pdo->query('SELECT COUNT(*) FROM auth_tokens')->fetchColumn(), 'Tokens cascade with their user:');

        test_db_apply_sql_file($pdo, $schemaPath);
        $assertBaseline($pdo, 'Left behind:');
    },
];
