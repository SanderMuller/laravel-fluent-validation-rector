<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use Illuminate\Support\Facades\Validator;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use SanderMuller\FluentValidation\FluentRule;

/**
 * Pins runtime-validator equivalence between rule forms that the rector
 * treats as interchangeable. The fixture tests verify the rector's output
 * *text* matches expected; these tests verify the output *behavior* matches
 * — specifically for modal operators (bail, sometimes, required_*) whose
 * semantics are order-sensitive and mode-switching, not additive.
 *
 * If the rector's string-to-fluent mapping ever shifts these operators'
 * position or chain order in a way that Laravel's validator interprets
 * differently, these tests fail loudly instead of silently changing
 * validation behavior in downstream codebases.
 *
 * Each case declares invalid input plus two equivalent rule shapes (string
 * and fluent) and asserts both produce identical error messages.
 *
 * Uses Orchestra\Testbench for the Laravel container + facade bootstrap
 * because FluentRule's builder touches `Validator::` and `Rule::` facades
 * during rule materialization.
 */
final class ValidationEquivalenceTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $stringRules
     * @param  array<string, mixed>  $fluentRules
     */
    #[DataProvider('provideEquivalenceCases')]
    public function testStringAndFluentFormsProduceEquivalentErrors(
        array $data,
        array $stringRules,
        array $fluentRules,
    ): void {
        $stringValidator = Validator::make($data, $stringRules);
        $fluentValidator = Validator::make($data, $fluentRules);

        $this->assertSame(
            $stringValidator->errors()->messages(),
            $fluentValidator->errors()->messages(),
            'String and fluent forms produced divergent validation errors',
        );
    }

    /** @return iterable<string, array{data: array<string, mixed>, stringRules: array<string, mixed>, fluentRules: array<string, mixed>}> */
    public static function provideEquivalenceCases(): iterable
    {
        yield 'simple required+email+max — invalid email' => [
            'data' => ['email' => 'not-an-email'],
            'stringRules' => ['email' => 'required|email|max:255'],
            'fluentRules' => ['email' => FluentRule::email()->required()->max(255)],
        ];

        yield 'simple required+email+max — empty string (required fires)' => [
            'data' => ['email' => ''],
            'stringRules' => ['email' => 'required|email|max:255'],
            'fluentRules' => ['email' => FluentRule::email()->required()->max(255)],
        ];

        yield 'simple required+email+max — too long' => [
            'data' => ['email' => str_repeat('a', 256) . '@example.com'],
            'stringRules' => ['email' => 'required|email|max:255'],
            'fluentRules' => ['email' => FluentRule::email()->required()->max(255)],
        ];

        yield 'bail stops after first failure — empty input should only fail once' => [
            'data' => ['name' => ''],
            'stringRules' => ['name' => 'bail|required|string|min:3'],
            'fluentRules' => ['name' => FluentRule::string()->required()->min(3)->bail()],
        ];

        yield 'bail with non-string value — should only fail once on string rule' => [
            'data' => ['name' => 123],
            'stringRules' => ['name' => 'bail|required|string|min:3'],
            'fluentRules' => ['name' => FluentRule::string()->required()->min(3)->bail()],
        ];

        yield 'sometimes — field absent, no errors expected' => [
            'data' => [],
            'stringRules' => ['email' => 'sometimes|required|email'],
            'fluentRules' => ['email' => FluentRule::email()->sometimes()->required()],
        ];

        yield 'sometimes — field present but invalid' => [
            'data' => ['email' => 'bad'],
            'stringRules' => ['email' => 'sometimes|required|email'],
            'fluentRules' => ['email' => FluentRule::email()->sometimes()->required()],
        ];

        yield 'nullable — empty value accepted' => [
            'data' => ['email' => null],
            'stringRules' => ['email' => 'nullable|email'],
            'fluentRules' => ['email' => FluentRule::email()->nullable()],
        ];

        yield 'nullable — invalid value still fails' => [
            'data' => ['email' => 'bad'],
            'stringRules' => ['email' => 'nullable|email'],
            'fluentRules' => ['email' => FluentRule::email()->nullable()],
        ];

        yield 'required_without — one of two missing' => [
            'data' => ['first' => 'a'],
            'stringRules' => ['second' => 'required_without:first|string'],
            'fluentRules' => ['second' => FluentRule::string()->requiredWithout('first')],
        ];

        yield 'required_without — both missing (fails)' => [
            'data' => [],
            'stringRules' => ['second' => 'required_without:first|string'],
            'fluentRules' => ['second' => FluentRule::string()->requiredWithout('first')],
        ];

        yield 'integer + min/max — too small' => [
            'data' => ['count' => 2],
            'stringRules' => ['count' => 'required|integer|min:5|max:100'],
            'fluentRules' => ['count' => FluentRule::integer()->required()->min(5)->max(100)],
        ];

        yield 'integer + min/max — too large' => [
            'data' => ['count' => 500],
            'stringRules' => ['count' => 'required|integer|min:5|max:100'],
            'fluentRules' => ['count' => FluentRule::integer()->required()->min(5)->max(100)],
        ];

        yield 'in — value not in list' => [
            'data' => ['role' => 'banned'],
            'stringRules' => ['role' => 'required|string|in:admin,editor,viewer'],
            'fluentRules' => ['role' => FluentRule::string()->required()->in(['admin', 'editor', 'viewer'])],
        ];

        yield 'array with typed children — items.* nested' => [
            'data' => ['items' => ['a', 123, 'c']],
            'stringRules' => [
                'items' => 'required|array',
                'items.*' => 'string|max:50',
            ],
            'fluentRules' => [
                'items' => FluentRule::array()->required()->each(FluentRule::string()->max(50)),
            ],
        ];

        yield 'boolean — non-boolean value' => [
            'data' => ['accepted' => 'yes'],
            'stringRules' => ['accepted' => 'required|boolean'],
            'fluentRules' => ['accepted' => FluentRule::boolean()->required()],
        ];
    }
}
