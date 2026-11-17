<?php
// Minimal assertion helpers for the plain PHP test runner (tests/run.php).
// Do not use PHP's assert(): it depends on zend.assertions.

declare(strict_types=1);

final class TestFailure extends Exception
{
}

function test_export(mixed $value): string
{
    return var_export($value, true);
}

function assert_true(mixed $value, string $message = ''): void
{
    if ($value !== true) {
        throw new TestFailure(trim($message . ' Expected true, got ' . test_export($value)));
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new TestFailure(trim(
            $message . ' Expected ' . test_export($expected) . ', got ' . test_export($actual)
        ));
    }
}

function assert_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        $shown = strlen($haystack) > 200 ? substr($haystack, 0, 200) . '...' : $haystack;
        throw new TestFailure(trim(
            $message . ' Expected string containing ' . test_export($needle) . ', got ' . test_export($shown)
        ));
    }
}

/**
 * Asserts that $fn throws an instance of $class and returns it for further checks.
 */
function assert_throws(callable $fn, string $class, string $message = ''): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return $e;
        }
        throw new TestFailure(trim(
            $message . " Expected {$class} to be thrown, got " . get_class($e) . ': ' . $e->getMessage()
        ));
    }
    throw new TestFailure(trim($message . " Expected {$class} to be thrown, nothing was thrown"));
}
