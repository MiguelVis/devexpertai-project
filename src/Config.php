<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Loads the git-ignored PHP config file (an array returned by config/config.php).
 */
final class Config
{
    /**
     * @return array<string, mixed>
     */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(
                "Config file not found: {$path}. Copy config/config.example.php to config/config.php and fill in the values."
            );
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException("Config file must return an array: {$path}");
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadDefault(): array
    {
        return self::load(APP_ROOT . '/config/config.php');
    }
}
