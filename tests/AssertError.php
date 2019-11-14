<?php

namespace Personnummer\Tests;

use PHPUnit\Framework\TestCase;

trait AssertError
{
    private $errors;

    /**
     * AssertError.
     *
     * @param callable    $callable
     * @param int|null    $error_type
     * @param string|null $error_msg
     * @param string|null $error_file
     * @param int|null    $error_line
     */
    public function assertError(
        callable $callable,
        ?int $error_type = null,
        ?string $error_msg = null,
        ?string $error_file = null,
        ?int $error_line = null
    ): void {
        $this->errors = [];

        set_error_handler(function (
            ?int $error_type = null,
            ?string $error_msg = null,
            ?string $error_file = null,
            ?int $error_line = null
        ) {
            $this->errors[] = compact('error_type', 'error_msg', 'error_file', 'error_line');
        });
        $callable();
        restore_error_handler();

        $comparisons = array_filter(compact('error_type', 'error_msg', 'error_file', 'error_line'), function ($value) {
            return !is_null($value);
        });

        $matchingErrors = [];
        foreach ($this->errors as $error) {
            if (empty(array_diff($comparisons, $error))) {
                $matchingErrors[] = $error;
            }
        }

        if (empty($matchingErrors)) {
            $failMessage = 'Expected error was not found';
            $failMessage .= $comparisons ? ': ' : '';
            $failMessage .= implode(', ', array_map(function ($value, $key) {
                return $key . ': ' . $value;
            }, $comparisons, array_keys($comparisons)));

            TestCase::fail($failMessage);
        }

        TestCase::assertTrue(true);
    }
}
