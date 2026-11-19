<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate;

use Illuminate\Http\Request;
use Illuminate\Support\ValidatedInput;

/*
 * Runs the ternaries the rule removes against the bare date() read, on both
 * receivers the rule accepts, over the same input.
 */

const MISSING = '__missing__';

/**
 * @return list<ValidatedInput|Request>
 */
function dateReceiversFor(mixed $value): array
{
    $input = $value === MISSING ? [] : ['published_at' => $value];

    return [new ValidatedInput($input), new Request($input)];
}

it('gives the same result as each removed check', function (mixed $value): void {
    foreach (dateReceiversFor($value) as $receiver) {
        $expected = $receiver->date('published_at')?->toIso8601String();

        expect(($receiver->filled('published_at') ? $receiver->date('published_at') : null)?->toIso8601String())->toBe($expected)
            ->and(($receiver->isNotFilled('published_at') ? null : $receiver->date('published_at'))?->toIso8601String())->toBe($expected);
    }
})->with([
    'missing key' => [MISSING],
    'null' => [null],
    'empty string' => [''],
    'whitespace' => ['  '],
    'date' => ['2024-01-31'],
    'date time' => ['2024-01-31 10:00:00'],
]);

it('differs for has() on a key that data_get() reads differently, which the rule skips', function (): void {
    $input = ['posts' => [['published_at' => '2024-01-31']]];

    foreach ([new ValidatedInput($input), new Request($input)] as $receiver) {
        expect($receiver->has('posts.{first}.published_at') ? $receiver->date('posts.{first}.published_at') : null)->toBeNull()
            ->and($receiver->date('posts.{first}.published_at'))->not->toBeNull();
    }
});
