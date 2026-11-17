<?php

declare(strict_types=1);

return [
    'assert_true passes on true' => function (): void {
        assert_true(true);
    },
    'assert_true throws TestFailure on non-true' => function (): void {
        assert_throws(fn () => assert_true(1), TestFailure::class);
        assert_throws(fn () => assert_true(false), TestFailure::class);
    },
    'assert_same passes on identical values' => function (): void {
        assert_same(['a' => 1], ['a' => 1]);
    },
    'assert_same throws TestFailure with expected and actual' => function (): void {
        $e = assert_throws(fn () => assert_same(1, '1'), TestFailure::class);
        assert_contains("Expected 1, got '1'", $e->getMessage());
    },
    'assert_contains passes when substring is present' => function (): void {
        assert_contains('lo w', 'hello world');
    },
    'assert_contains throws TestFailure when substring is missing' => function (): void {
        assert_throws(fn () => assert_contains('xyz', 'hello'), TestFailure::class);
    },
    'assert_throws returns the matching exception' => function (): void {
        $e = assert_throws(function (): void {
            throw new RuntimeException('boom');
        }, RuntimeException::class);
        assert_same('boom', $e->getMessage());
    },
    'assert_throws fails when nothing is thrown' => function (): void {
        $thrown = false;
        try {
            assert_throws(function (): void {
            }, RuntimeException::class);
        } catch (TestFailure $e) {
            $thrown = true;
        }
        assert_true($thrown);
    },
    'assert_throws fails when a different class is thrown' => function (): void {
        $thrown = false;
        try {
            assert_throws(function (): void {
                throw new LogicException('other');
            }, RuntimeException::class);
        } catch (TestFailure $e) {
            $thrown = true;
        }
        assert_true($thrown);
    },
];
