<?php
// Example configuration. Copy to config/config.php (git-ignored) and fill in real values.
// Never put real secrets in this file: it is committed.

declare(strict_types=1);

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'hotel_maintenance',
        // Dedicated MariaDB user: ALL on hotel_maintenance and hm_% only (see ARCHITECTURE.md).
        'user' => 'hm_app',
        'password' => '', // fill in config/config.php only; never commit it
        'charset' => 'utf8mb4',
    ],
    'llm' => [
        'default_provider' => 'anthropic',
        'providers' => [
            'anthropic' => [
                'base_url' => 'https://api.anthropic.com',
                'api_key' => 'CHANGE_ME',
                'model' => 'CHANGE_ME',
            ],
            'devexpert' => [
                'base_url' => 'https://inference.devexpert.io',
                'api_key' => 'CHANGE_ME',
                'model' => 'chat',
            ],
        ],
    ],
    'mcp' => [
        'api_key' => 'CHANGE_ME',
    ],
    'service_account' => [
        'username' => 'mcp-service',
        'password' => 'CHANGE_ME',
    ],
    'auth' => [
        'token_ttl_hours' => 8,
    ],
    'log' => [
        'dir' => 'logs',
    ],
];
