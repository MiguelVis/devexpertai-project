<?php
// Shared bootstrap: root path, error settings and the App\ autoloader.
// Safe to require more than once.

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));

    error_reporting(E_ALL);
    // Never print HTML errors into JSON responses; log them instead.
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = APP_ROOT . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}
