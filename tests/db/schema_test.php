<?php
// Live tests for db/schema.sql. They run only against a disposable hm_* database named in
// config/config.php (db.name); otherwise the file prints one SKIP line and returns no tests.
// Every test re-applies db/schema.sql first, and the last one leaves an empty schema.

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
    'users',
    'work_order_status_history',
    'work_orders',
];

$fresh = static function () use ($schemaPath): PDO {
    $pdo = test_db_connect();
    test_db_apply_sql_file($pdo, $schemaPath);
    return $pdo;
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
    $pdo->exec("INSERT INTO level3_typologies (level2_typology_id, name, default_priority) VALUES ({$l2}, 'Fuga en el lavabo', 'Media')");
    $l3 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO users (username, password_hash, first_name) VALUES ('tecnico1', 'x', 'Ana')");
    $userId = (int) $pdo->lastInsertId();
    return ['location' => $locationId, 'l1' => $l1, 'l2' => $l2, 'l3' => $l3, 'user' => $userId];
};

$insertWorkOrder = static function (PDO $pdo, array $v): void {
    $stmt = $pdo->prepare(
        'INSERT INTO work_orders (creator_user_id, assignee_user_id, status, location_id, level3_typology_id, priority, description)'
        . ' VALUES (:creator, :assignee, :status, :location, :l3, :priority, :description)'
    );
    $stmt->execute([
        'creator' => $v['creator'],
        'assignee' => $v['assignee'] ?? null,
        'status' => $v['status'] ?? 'Pendiente',
        'location' => $v['location'],
        'l3' => $v['l3'],
        'priority' => $v['priority'] ?? 'Media',
        'description' => $v['description'] ?? 'Gotea el grifo',
    ]);
};

return [
    'schema applies twice and creates exactly the 10 tables' => function () use ($fresh, $schemaPath, $expectedTables): void {
        $pdo = $fresh();
        test_db_apply_sql_file($pdo, $schemaPath);
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        sort($tables);
        assert_same($expectedTables, $tables);
        $rows = $pdo->query(
            'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        )->fetchAll();
        foreach ($rows as $row) {
            assert_same('InnoDB', $row['ENGINE'], "{$row['TABLE_NAME']} engine:");
            assert_same('utf8mb4_unicode_ci', $row['TABLE_COLLATION'], "{$row['TABLE_NAME']} collation:");
        }
        foreach ($expectedTables as $table) {
            assert_same(0, (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(), "{$table} is empty:");
        }
    },

    'foreign keys reference the expected tables with the expected delete rules' => function () use ($fresh): void {
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
            'level3_typologies.level2_typology_id' => 'level2_typologies.id RESTRICT/RESTRICT fk_level3_typologies_level2_typology_id',
            'locations.location_type_id' => 'location_types.id RESTRICT/RESTRICT fk_locations_location_type_id',
            'work_order_status_history.changed_by_user_id' => 'users.id RESTRICT/RESTRICT fk_work_order_status_history_changed_by_user_id',
            'work_order_status_history.work_order_id' => 'work_orders.id CASCADE/RESTRICT fk_work_order_status_history_work_order_id',
            'work_orders.assignee_user_id' => 'users.id RESTRICT/RESTRICT fk_work_orders_assignee_user_id',
            'work_orders.creator_user_id' => 'users.id RESTRICT/RESTRICT fk_work_orders_creator_user_id',
            'work_orders.level3_typology_id' => 'level3_typologies.id RESTRICT/RESTRICT fk_work_orders_level3_typology_id',
            'work_orders.location_id' => 'locations.id RESTRICT/RESTRICT fk_work_orders_location_id',
        ];
        assert_same($expected, $actual);
        $columns = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_orders'"
        )->fetchAll(PDO::FETCH_COLUMN);
        assert_true(!in_array('level1_typology_id', $columns, true) && !in_array('level2_typology_id', $columns, true), 'work_orders stores only the L3 typology:');
    },

    'typology tree is enforced (1452 on unknown parent, 1451 on deleting a parent with children)' => function () use ($fresh, $expectDbError): void {
        $pdo = $fresh();
        $expectDbError(1452, fn () => $pdo->exec("INSERT INTO level2_typologies (level1_typology_id, name) VALUES (999, 'x')"), 'L2 with unknown L1:');
        $expectDbError(1452, fn () => $pdo->exec("INSERT INTO level3_typologies (level2_typology_id, name, default_priority) VALUES (999, 'x', 'Baja')"), 'L3 with unknown L2:');
        $pdo->exec("INSERT INTO level1_typologies (name) VALUES ('Electricidad')");
        $l1 = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO level2_typologies (level1_typology_id, name) VALUES ({$l1}, 'Iluminación')");
        $l2 = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO level3_typologies (level2_typology_id, name, default_priority) VALUES ({$l2}, 'Bombilla fundida', 'Baja')");
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
        assert_same('Pendiente', $row['status']);
        assert_true(is_string($row['created_at']) && $row['created_at'] !== '', 'created_at defaulted:');
        $expectDbError(1451, fn () => $pdo->exec("DELETE FROM locations WHERE id = {$ids['location']}"), 'Delete referenced location:');
        $expectDbError(1451, fn () => $pdo->exec("DELETE FROM users WHERE id = {$ids['user']}"), 'Delete referenced creator:');
    },

    'Spanish status and priority round-trip and other values are rejected (4025)' => function () use ($fresh, $expectDbError, $seedMinimal, $insertWorkOrder): void {
        $pdo = $fresh();
        $mode = (string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        assert_true(!str_contains($mode, 'STRICT_'), "Proven under the non-strict server default sql_mode ({$mode}):");
        $ids = $seedMinimal($pdo);
        $valid = ['creator' => $ids['user'], 'location' => $ids['location'], 'l3' => $ids['l3']];
        $insertWorkOrder($pdo, ['status' => 'En resolución', 'priority' => 'Alta', 'assignee' => $ids['user']] + $valid);
        $workOrderId = (int) $pdo->lastInsertId();
        $row = $pdo->query("SELECT status, priority, HEX(status) AS hex FROM work_orders WHERE id = {$workOrderId}")->fetch();
        assert_same('En resolución', $row['status']);
        assert_same('Alta', $row['priority']);
        assert_same(strtoupper(bin2hex('En resolución')), $row['hex'], 'Stored bytes are UTF-8:');
        $history = $pdo->prepare('INSERT INTO work_order_status_history (work_order_id, status, changed_by_user_id) VALUES (?, ?, ?)');
        $history->execute([$workOrderId, 'En resolución', $ids['user']]);
        assert_same('En resolución', $pdo->query('SELECT status FROM work_order_status_history')->fetchColumn());
        foreach (['Pendiente', 'Asignado', 'Resuelto', 'Cancelado'] as $status) {
            $insertWorkOrder($pdo, ['status' => $status] + $valid);
        }
        foreach (['Baja', 'Media'] as $priority) {
            $insertWorkOrder($pdo, ['priority' => $priority] + $valid);
        }
        // 'Pendiente ' and 'En resolución ' prove the CHECK compares bytes (utf8mb4_bin is PAD SPACE).
        foreach (['Abierto', 'En resolucion', 'pendiente', '', 'Pendiente ', 'En resolución '] as $bad) {
            $expectDbError(4025, fn () => $insertWorkOrder($pdo, ['status' => $bad] + $valid), "Work order status '{$bad}':");
            $expectDbError(4025, fn () => $history->execute([$workOrderId, $bad, $ids['user']]), "History status '{$bad}':");
        }
        foreach (['Urgente', 'alta', '', 'Alta '] as $bad) {
            $expectDbError(4025, fn () => $insertWorkOrder($pdo, ['priority' => $bad] + $valid), "Work order priority '{$bad}':");
            $expectDbError(4025, fn () => $pdo->exec("INSERT INTO level3_typologies (level2_typology_id, name, default_priority) VALUES ({$ids['l2']}, 'Otra', '{$bad}')"), "L3 default priority '{$bad}':");
        }
        $expectDbError(4025, fn () => $pdo->exec("UPDATE work_orders SET status = 'Abierto' WHERE id = {$workOrderId}"), 'Update to bad status:');
        $pdo->exec("DELETE FROM work_orders WHERE id = {$workOrderId}");
        assert_same(0, (int) $pdo->query('SELECT COUNT(*) FROM work_order_status_history')->fetchColumn(), 'History cascades with its work order:');
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

    'users defaults, unique username and token cascade; leaves an empty schema' => function () use ($fresh, $schemaPath, $expectDbError, $expectedTables): void {
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
        foreach ($expectedTables as $table) {
            assert_same(0, (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(), "{$table} left empty:");
        }
    },
];
