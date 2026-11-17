<?php
// Plain PHP test runner. Usage: php tests/run.php [filter]
// Finds tests/**/*_test.php; each file returns array<string, callable>.
// Exits 1 on any failure, on a file that does not return an array, or when no tests ran.

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
foreach (glob(__DIR__ . '/lib/*.php') ?: [] as $lib) {
    require_once $lib;
}

$filter = $argv[1] ?? null;
$testsDir = __DIR__;

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testsDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), '_test.php')) {
        $files[] = $file->getPathname();
    }
}
sort($files);

$total = 0;
$failures = 0;

foreach ($files as $path) {
    $relative = 'tests/' . str_replace('\\', '/', substr($path, strlen($testsDir) + 1));

    // Isolated scope so test files cannot clobber the runner's variables.
    $tests = (static fn (string $testFile): mixed => require $testFile)($path);
    if (!is_array($tests)) {
        echo "FAIL {$relative}: file does not return an array of tests\n";
        $total++;
        $failures++;
        continue;
    }

    foreach ($tests as $name => $test) {
        if ($filter !== null && !str_contains($relative, $filter) && !str_contains((string) $name, $filter)) {
            continue;
        }
        $total++;
        try {
            if (!is_callable($test)) {
                throw new TestFailure('test is not callable');
            }
            $test();
            echo "PASS {$relative} :: {$name}\n";
        } catch (Throwable $e) {
            $failures++;
            $message = str_replace(["\r", "\n"], ' ', $e->getMessage());
            echo "FAIL {$relative} :: {$name}: {$message}\n";
        }
    }
}

echo "{$total} tests, {$failures} failures\n";

if ($total === 0) {
    echo "No tests found.\n";
    exit(1);
}
exit($failures > 0 ? 1 : 0);
