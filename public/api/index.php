<?php
// API entry point placeholder. The api-post-json-router feature replaces this body.

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'service' => 'hotel-maintenance-api']);
