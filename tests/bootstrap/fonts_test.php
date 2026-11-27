<?php

declare(strict_types=1);

const TEXT_FONT_REL = 'assets/vendor/source-sans-3/SourceSans3-latin.woff2';

return [
    'vendored Source Sans 3 font matches the SHA-256 in SOURCE.txt' => function (): void {
        $dir = APP_ROOT . '/public/assets/vendor/source-sans-3';
        $source = (string) file_get_contents($dir . '/SOURCE.txt');
        $matched = preg_match('/SHA-256:\s+([0-9a-f]{64})\s+SourceSans3-latin\.woff2/', $source, $m);
        assert_same(1, $matched, 'SOURCE.txt has no SHA-256 line for the font.');
        assert_same($m[1], hash_file('sha256', APP_ROOT . '/public/' . TEXT_FONT_REL));
        assert_true(is_file($dir . '/OFL.txt'), 'OFL.txt is missing.');
    },

    'fonts.css points at the vendored font with a relative URL' => function (): void {
        $css = (string) file_get_contents(APP_ROOT . '/public/assets/css/fonts.css');
        assert_contains("font-family: 'Source Sans 3';", $css);
        assert_contains('url(../vendor/source-sans-3/SourceSans3-latin.woff2)', $css);
        assert_true(!str_contains($css, 'googleapis') && !str_contains($css, 'gstatic'), 'fonts.css must not load from Google.');
    },

    'built-in server serves fonts.css and the font' => function (): void {
        $server = start_dev_server(APP_ROOT . '/public');
        try {
            $base = $server['base_url'];

            $css = http_request('GET', "{$base}/assets/css/fonts.css");
            assert_same(200, $css['status'], 'GET fonts.css:');

            $font = http_request('GET', "{$base}/" . TEXT_FONT_REL);
            assert_same(200, $font['status'], 'GET font:');
            assert_same(28872, strlen($font['body']), 'Font size:');
            assert_same('wOF2', substr($font['body'], 0, 4));
        } finally {
            stop_dev_server($server);
        }
    },
];
