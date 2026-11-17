<?php

declare(strict_types=1);

return [
    'built-in server serves the page, the vendored W3.CSS and the API entry point' => function (): void {
        $server = start_dev_server(APP_ROOT . '/public');
        try {
            $base = $server['base_url'];

            $page = http_request('GET', "{$base}/");
            assert_same(200, $page['status'], 'GET /:');
            assert_contains('data-app="hotel-maintenance"', $page['body']);
            assert_contains('href="assets/vendor/w3css/w3.css"', $page['body']);

            $css = http_request('GET', "{$base}/assets/vendor/w3css/w3.css");
            assert_same(200, $css['status'], 'GET w3.css:');
            assert_contains('W3.CSS 5.01', $css['body']);

            $api = http_request('POST', "{$base}/api/", '{}');
            assert_same(200, $api['status'], 'POST /api/:');
            $type = (string) http_header($api['headers'], 'Content-Type');
            assert_true(str_starts_with($type, 'application/json'), "Content-Type was '{$type}'.");
            $json = json_decode($api['body'], true);
            assert_true(is_array($json), 'API body is not a JSON object.');
            assert_same(true, $json['ok'] ?? null);
            assert_same('hotel-maintenance-api', $json['service'] ?? null);
        } finally {
            stop_dev_server($server);
        }
    },
];
