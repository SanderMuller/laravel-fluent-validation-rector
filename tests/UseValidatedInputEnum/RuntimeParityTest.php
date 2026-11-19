<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum;

use Illuminate\Http\Request;
use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostFlag;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostRating;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostVisibility;
use TypeError;

/*
 * Runs the tryFrom() reads the rule rewrites against the enum() accessor it
 * emits, on both receivers it accepts. The blank-case enum the rule skips and
 * the known array difference are pinned here too.
 */

const MISSING = '__missing__';

/**
 * @return list<ValidatedInput|Request>
 */
function enumReceiversFor(mixed $value): array
{
    $input = $value === MISSING ? [] : ['status' => $value];

    return [new ValidatedInput($input), new Request($input)];
}

it('gives the same case as tryFrom() over the string read', function (mixed $value): void {
    foreach (enumReceiversFor($value) as $receiver) {
        expect($receiver->enum('status', PostStatus::class))
            ->toBe(PostStatus::tryFrom($receiver->str('status')->toString()));
    }
})->with([
    'missing key' => [MISSING],
    'null' => [null],
    'empty string' => [''],
    'whitespace' => ['  '],
    'case value' => ['draft'],
    'other case value' => ['published'],
    'unknown value' => ['archived'],
    'case value with different case' => ['Draft'],
    'padded case value' => [' draft '],
    'int' => [5],
    'true' => [true],
    'false' => [false],
    'float' => [1.5],
]);

it('differs for an enum with a blank case value, which the rule skips', function (): void {
    foreach (enumReceiversFor('') as $receiver) {
        expect(PostVisibility::tryFrom($receiver->str('status')->toString()))->toBe(PostVisibility::Unset)
            ->and($receiver->enum('status', PostVisibility::class))->toBeNull();
    }
});

it("differs for false with a '0' case value, which the rule skips", function (): void {
    foreach (enumReceiversFor(false) as $receiver) {
        expect(PostFlag::tryFrom($receiver->str('status')->toString()))->toBeNull()
            ->and($receiver->enum('status', PostFlag::class))->toBe(PostFlag::Off);
    }
});

it('differs for a float with a numeric case value, which the rule skips', function (): void {
    foreach (enumReceiversFor(1.5) as $receiver) {
        expect(PostRating::tryFrom($receiver->str('status')->toString()))->toBeNull()
            ->and(@$receiver->enum('status', PostRating::class))->toBe(PostRating::One);
    }
});

it('differs for an array', function (): void {
    foreach (enumReceiversFor(['draft']) as $receiver) {
        expect(@PostStatus::tryFrom($receiver->str('status')->toString()))->toBeNull()
            ->and(fn () => $receiver->enum('status', PostStatus::class))->toThrow(TypeError::class);
    }
});
