<?php
// Short-lived PHP built-in server for serving tests.
// Always use the array form of proc_open: the string form goes through cmd.exe
// on Windows and terminating it can orphan the php.exe server.

declare(strict_types=1);

/**
 * @return array{process: resource, port: int, base_url: string}
 */
function start_dev_server(string $docroot): array
{
    $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($probe === false) {
        throw new RuntimeException("Could not find a free port: {$errstr}");
    }
    $name = stream_socket_get_name($probe, false);
    fclose($probe);
    $port = (int) substr((string) $name, strrpos((string) $name, ':') + 1);

    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $process = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $docroot],
        [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the PHP built-in server');
    }

    $server = ['process' => $process, 'port' => $port, 'base_url' => "http://127.0.0.1:{$port}"];
    $deadline = microtime(true) + 5.0;
    while (microtime(true) < $deadline) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
        if ($socket !== false) {
            fclose($socket);
            return $server;
        }
        usleep(50_000);
    }

    stop_dev_server($server);
    throw new RuntimeException("PHP built-in server did not become ready on port {$port} within 5 s");
}

/**
 * @param array{process: resource} $server
 */
function stop_dev_server(array $server): void
{
    if (is_resource($server['process'])) {
        proc_terminate($server['process']);
        proc_close($server['process']);
    }
}

/**
 * @return array{status: int, headers: array<int, string>, body: string}
 */
function http_request(string $method, string $url, ?string $body = null): array
{
    $options = [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 5,
        'header' => "Connection: close\r\n",
    ];
    if ($body !== null) {
        $options['header'] .= "Content-Type: application/json\r\n";
        $options['content'] = $body;
    }
    $context = stream_context_create(['http' => $options]);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        throw new RuntimeException("HTTP request failed: {$method} {$url}");
    }

    $headers = $http_response_header ?? [];
    $status = 0;
    if (isset($headers[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $headers[0], $m) === 1) {
        $status = (int) $m[1];
    }

    return ['status' => $status, 'headers' => $headers, 'body' => $result];
}

/**
 * Returns the value of the first header named $name (case-insensitive), or null.
 *
 * @param array<int, string> $headers
 */
function http_header(array $headers, string $name): ?string
{
    foreach ($headers as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2 && strcasecmp(trim($parts[0]), $name) === 0) {
            return trim($parts[1]);
        }
    }
    return null;
}
