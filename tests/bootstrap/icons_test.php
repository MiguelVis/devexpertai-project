<?php

declare(strict_types=1);

const ICON_FONT_REL = 'assets/vendor/material-symbols/MaterialSymbolsOutlined.woff2';

return [
    'vendored Material Symbols font matches the SHA-256 in SOURCE.txt' => function (): void {
        $dir = APP_ROOT . '/public/assets/vendor/material-symbols';
        $source = (string) file_get_contents($dir . '/SOURCE.txt');
        $matched = preg_match('/SHA-256:\s+([0-9a-f]{64})\s+MaterialSymbolsOutlined\.woff2/', $source, $m);
        assert_same(1, $matched, 'SOURCE.txt has no SHA-256 line for the font.');
        assert_same($m[1], hash_file('sha256', APP_ROOT . '/public/' . ICON_FONT_REL));
        assert_true(is_file($dir . '/LICENSE'), 'LICENSE is missing.');
    },

    'icons.css points at the vendored font with a relative URL' => function (): void {
        $css = (string) file_get_contents(APP_ROOT . '/public/assets/css/icons.css');
        assert_contains('url(../vendor/material-symbols/MaterialSymbolsOutlined.woff2)', $css);
        assert_contains('.material-symbols-outlined', $css);
        assert_true(!str_contains($css, 'googleapis') && !str_contains($css, 'gstatic'), 'icons.css must not load from Google.');
    },

    'built-in server serves icons.css and the font' => function (): void {
        $server = start_dev_server(APP_ROOT . '/public');
        try {
            $base = $server['base_url'];

            $css = http_request('GET', "{$base}/assets/css/icons.css");
            assert_same(200, $css['status'], 'GET icons.css:');

            $font = http_request('GET', "{$base}/" . ICON_FONT_REL);
            assert_same(200, $font['status'], 'GET font:');
            assert_same(325336, strlen($font['body']), 'Font size:');
            assert_same('wOF2', substr($font['body'], 0, 4));
        } finally {
            stop_dev_server($server);
        }
    },
];
