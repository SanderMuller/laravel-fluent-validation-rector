<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString;

use Illuminate\Http\UploadedFile;
use SanderMuller\FluentValidation\ValidatedInput;

/*
 * Runs the expressions the two rules rewrite against the accessors they emit,
 * over the same validated values. The known differences of the nullableString
 * rewrite are pinned here, so the rule's docblock matches what happens.
 */

const MISSING = '__missing__';

function validatedInputFor(mixed $value): ValidatedInput
{
    return new ValidatedInput($value === MISSING ? [] : ['field' => $value]);
}

function filledStringTernary(ValidatedInput $safe): string
{
    return $safe->filled('field') ? $safe->string('field')->value() : 'fallback';
}

function uploadedFile(): UploadedFile
{
    return new UploadedFile(__FILE__, 'upload.php', null, null, true);
}

beforeEach(function (): void {
    if (! method_exists(ValidatedInput::class, 'nullableString')) {
        $this->markTestSkipped('The installed laravel-fluent-validation has no ValidatedInput::nullableString().');
    }
});

it('gives the same string for every value a string field can hold', function (mixed $value): void {
    $safe = validatedInputFor($value);

    expect($safe->nullableString('field', 'fallback'))->toBe(filledStringTernary($safe));
})->with([
    'missing key' => [MISSING],
    'null' => [null],
    'empty string' => [''],
    'whitespace' => ['  '],
    'string' => ['a'],
    'padded string is not trimmed' => [' a '],
    'zero string' => ['0'],
    'int zero' => [0],
    'float' => [1.5],
]);

it('differs for a bool, an array or an object', function (mixed $value, string $ternary): void {
    $safe = validatedInputFor($value);

    expect(@filledStringTernary($safe))->toBe($ternary)
        ->and($safe->nullableString('field', 'fallback'))->toBe('fallback');
})->with([
    'true' => [true, '1'],
    'false' => [false, ''],
    'empty array' => [[], 'Array'],
    'list' => [['x'], 'Array'],
    'uploaded file' => [uploadedFile(), __FILE__],
]);

it('gives the same result for file() as the instanceof ternary', function (mixed $value): void {
    $safe = validatedInputFor($value);

    if (! method_exists($safe, 'file')) {
        $this->markTestSkipped('The installed laravel-fluent-validation has no ValidatedInput::file().');
    }

    $ternary = $safe->input('field') instanceof UploadedFile ? $safe->input('field') : null;

    expect($safe->file('field'))->toBe($ternary);
})->with([
    'missing key' => [MISSING],
    'null' => [null],
    'string' => ['a'],
    'array' => [['x']],
    'uploaded file' => [uploadedFile()],
]);
