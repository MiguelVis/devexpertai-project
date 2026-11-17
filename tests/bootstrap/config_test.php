<?php

declare(strict_types=1);

use App\Config;

return [
    'example config loads and has every required key path' => function (): void {
        $config = Config::load(APP_ROOT . '/config/config.example.php');
        $paths = [
            'db.host', 'db.port', 'db.name', 'db.user', 'db.password', 'db.charset',
            'llm.default_provider',
            'llm.providers.anthropic.base_url', 'llm.providers.anthropic.api_key', 'llm.providers.anthropic.model',
            'llm.providers.devexpert.base_url', 'llm.providers.devexpert.api_key', 'llm.providers.devexpert.model',
            'mcp.api_key',
            'service_account.username', 'service_account.password',
            'auth.token_ttl_hours',
            'log.dir',
        ];
        foreach ($paths as $path) {
            $node = $config;
            foreach (explode('.', $path) as $key) {
                assert_true(is_array($node) && array_key_exists($key, $node), "Missing config key {$path}.");
                $node = $node[$key];
            }
        }
        assert_same('anthropic', $config['llm']['default_provider']);
        assert_same(8, $config['auth']['token_ttl_hours']);
    },
    'example config holds placeholder secrets only' => function (): void {
        $config = Config::load(APP_ROOT . '/config/config.example.php');
        assert_same('CHANGE_ME', $config['llm']['providers']['anthropic']['api_key']);
        assert_same('CHANGE_ME', $config['llm']['providers']['devexpert']['api_key']);
        assert_same('CHANGE_ME', $config['mcp']['api_key']);
        assert_same('CHANGE_ME', $config['service_account']['password']);
    },
    'Config::load on a missing path throws RuntimeException naming the example' => function (): void {
        $missing = APP_ROOT . '/config/does-not-exist.php';
        $e = assert_throws(fn () => Config::load($missing), RuntimeException::class);
        assert_contains('config.example.php', $e->getMessage());
        assert_contains('does-not-exist.php', $e->getMessage());
    },
    '.gitignore ignores config/config.php' => function (): void {
        $lines = file(APP_ROOT . '/.gitignore', FILE_IGNORE_NEW_LINES) ?: [];
        $lines = array_map('trim', $lines);
        assert_true(in_array('/config/config.php', $lines, true), '.gitignore lacks /config/config.php.');
    },
];
