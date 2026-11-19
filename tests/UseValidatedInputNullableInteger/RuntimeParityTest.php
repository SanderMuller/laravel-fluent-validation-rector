<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger;

use SanderMuller\FluentValidation\ValidatedInput;

/*
 * Runs the ternary the rule rewrites against the nullableInteger() call it
 * emits, over the same validated values.
 */

const MISSING = '__missing__';

beforeEach(function (): void {
    if (! method_exists(ValidatedInput::class, 'nullableInteger')) {
        $this->markTestSkipped('The installed laravel-fluent-validation has no ValidatedInput::nullableInteger().');
    }
});

it('gives the same result as the filled() ternary for every value', function (mixed $value): void {
    $safe = new ValidatedInput($value === MISSING ? [] : ['rank' => $value]);

    expect($safe->nullableInteger('rank'))->toBe($safe->filled('rank') ? $safe->integer('rank') : null)
        ->and($safe->nullableInteger('rank', 7))->toBe($safe->filled('rank') ? $safe->integer('rank') : 7);
})->with([
    'missing key' => [MISSING],
    'null' => [null],
    'empty string' => [''],
    'whitespace' => ['  '],
    'int' => [3],
    'zero' => [0],
    'numeric string' => ['42'],
    'padded numeric string' => [' 42 '],
    'float' => [1.5],
    'non-numeric string' => ['abc'],
    'true' => [true],
    'false' => [false],
]);
