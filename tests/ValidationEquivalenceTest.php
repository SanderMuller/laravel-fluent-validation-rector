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
     * @param  array<string, string>  $attributeNames
     * @param  array<string, string>  $customMessages
     * @param  array<string, string>  $langLines
     * @param  list<string>|null  $expectedErrorKeys
     */
    #[DataProvider('provideEquivalenceCases')]
    public function testStringAndFluentFormsProduceEquivalentErrors(
        array $data,
        array $stringRules,
        array $fluentRules,
        array $attributeNames = [],
        array $customMessages = [],
        ?string $expectedMessagesContain = null,
        array $langLines = [],
        ?array $expectedErrorKeys = null,
        ?int $expectedMessageCount = null,
    ): void {
        if ($langLines !== []) {
            $translator = $this->app['translator'];
            // Force-load Laravel's packaged validation lang file first.
            // Without this, addLines() seeds the `validation` group with
            // only the lines we register, marking it "loaded" and
            // skipping the framework file — every other validation key
            // would then render as its dot-path. Testbench creates a
            // fresh app per test, so this doesn't bleed across rows.
            $translator->load('*', 'validation', 'en');
            $translator->addLines($langLines, 'en');
        }

        $stringValidator = Validator::make($data, $stringRules);
        $fluentValidator = Validator::make($data, $fluentRules);

        if ($attributeNames !== []) {
            $stringValidator->setAttributeNames($attributeNames);
            $fluentValidator->setAttributeNames($attributeNames);
        }

        if ($customMessages !== []) {
            $stringValidator->setCustomMessages($customMessages);
            $fluentValidator->setCustomMessages($customMessages);
        }

        $this->assertSame(
            $stringValidator->errors()->messages(),
            $fluentValidator->errors()->messages(),
            'String and fluent forms produced divergent validation errors',
        );

        if ($expectedMessagesContain !== null) {
            $flattened = implode("\n", array_merge(...array_values($stringValidator->errors()->messages())));
            $this->assertStringContainsString(
                $expectedMessagesContain,
                $flattened,
                'Expected marker string missing from rendered messages — override may not have applied',
            );
        }

        // Independent pins for outcome/shape so rows survive the "both
        // sides degrade identically" scenario (framework upgrade,
        // translator change) where `assertSame` would stay silent.
        if ($expectedErrorKeys !== null) {
            $this->assertSame(
                $expectedErrorKeys,
                array_keys($stringValidator->errors()->messages()),
                'String-form error-bag key shape diverged from pinned expectation',
            );
            $this->assertSame(
                $expectedErrorKeys,
                array_keys($fluentValidator->errors()->messages()),
                'Fluent-form error-bag key shape diverged from pinned expectation',
            );
        }

        if ($expectedMessageCount !== null) {
            $flatten = static fn (array $m): int => array_sum(array_map(count(...), $m));
            $this->assertSame(
                $expectedMessageCount,
                $flatten($stringValidator->errors()->messages()),
                'String-form message count diverged from pinned expectation',
            );
            $this->assertSame(
                $expectedMessageCount,
                $flatten($fluentValidator->errors()->messages()),
                'Fluent-form message count diverged from pinned expectation',
            );
        }
    }

    /** @return iterable<string, array{data: array<string, mixed>, stringRules: array<string, mixed>, fluentRules: array<string, mixed>, attributeNames?: array<string, string>, customMessages?: array<string, string>, expectedMessagesContain?: string, langLines?: array<string, string>, expectedErrorKeys?: list<string>, expectedMessageCount?: int}> */
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
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => null,
            'langLines' => [],
            'expectedErrorKeys' => ['name'],
            'expectedMessageCount' => 1,
        ];

        yield 'bail with non-string value — should only fail once on string rule' => [
            'data' => ['name' => 123],
            'stringRules' => ['name' => 'bail|required|string|min:3'],
            'fluentRules' => ['name' => FluentRule::string()->required()->min(3)->bail()],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => null,
            'langLines' => [],
            'expectedErrorKeys' => ['name'],
            'expectedMessageCount' => 1,
        ];

        yield 'sometimes — field absent, no errors expected' => [
            'data' => [],
            'stringRules' => ['email' => 'sometimes|required|email'],
            'fluentRules' => ['email' => FluentRule::email()->sometimes()->required()],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => null,
            'langLines' => [],
            'expectedErrorKeys' => [],
            'expectedMessageCount' => 0,
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

        // --- Phase 1: setAttributeNames() parity ---
        // Each pair: label-applied case must NOT emit ->label() in fluent
        // form. setAttributeNames takes precedence; pre/post rector output
        // must stay identical whether the user registers a rename or not.

        yield 'email + setAttributeNames — renamed label' => [
            'data' => ['email' => ''],
            'stringRules' => ['email' => 'required|email'],
            'fluentRules' => ['email' => FluentRule::email()->required()],
            'attributeNames' => ['email' => 'E-mail address'],
            'customMessages' => [],
            'expectedMessagesContain' => 'E-mail address',
        ];

        yield 'url + setAttributeNames — renamed label' => [
            'data' => ['homepage' => 'not-a-url'],
            'stringRules' => ['homepage' => 'required|url'],
            'fluentRules' => ['homepage' => FluentRule::url()->required()],
            'attributeNames' => ['homepage' => 'Website URL'],
            'customMessages' => [],
            'expectedMessagesContain' => 'Website URL',
        ];

        yield 'uuid + setAttributeNames — renamed label' => [
            'data' => ['token' => 'not-a-uuid'],
            'stringRules' => ['token' => 'required|uuid'],
            'fluentRules' => ['token' => FluentRule::uuid()->required()],
            'attributeNames' => ['token' => 'Access Token'],
            'customMessages' => [],
            'expectedMessagesContain' => 'Access Token',
        ];

        yield 'string+max + setAttributeNames — renamed label' => [
            'data' => ['name' => str_repeat('a', 260)],
            'stringRules' => ['name' => 'required|string|max:255'],
            'fluentRules' => ['name' => FluentRule::string()->required()->max(255)],
            'attributeNames' => ['name' => 'Full Name'],
            'customMessages' => [],
            'expectedMessagesContain' => 'Full Name',
        ];

        yield 'integer+min + setAttributeNames — renamed label' => [
            'data' => ['count' => 1],
            'stringRules' => ['count' => 'required|integer|min:5'],
            'fluentRules' => ['count' => FluentRule::integer()->required()->min(5)],
            'attributeNames' => ['count' => 'Item Count'],
            'customMessages' => [],
            'expectedMessagesContain' => 'Item Count',
        ];

        yield 'required_with + setAttributeNames — renamed label' => [
            'data' => ['first' => 'a'],
            'stringRules' => ['second' => 'required_with:first|string'],
            'fluentRules' => ['second' => FluentRule::string()->requiredWith('first')],
            'attributeNames' => ['second' => 'Second Field', 'first' => 'First Field'],
            'customMessages' => [],
            'expectedMessagesContain' => 'Second Field',
        ];

        yield 'nullable+email + setAttributeNames — invalid value with rename' => [
            'data' => ['email' => 'bad'],
            'stringRules' => ['email' => 'nullable|email'],
            'fluentRules' => ['email' => FluentRule::email()->nullable()],
            'attributeNames' => ['email' => 'E-mail address'],
            'customMessages' => [],
            'expectedMessagesContain' => 'E-mail address',
        ];

        yield 'boolean + setAttributeNames — renamed label' => [
            'data' => ['accepted' => 'yes'],
            'stringRules' => ['accepted' => 'required|boolean'],
            'fluentRules' => ['accepted' => FluentRule::boolean()->required()],
            'attributeNames' => ['accepted' => 'Terms Acceptance'],
            'customMessages' => [],
            'expectedMessagesContain' => 'Terms Acceptance',
        ];

        // --- Phase 2: setCustomMessages() parity ---
        // Flat `{attribute}.{rule}` keys and wildcard
        // `{attribute.*.nested}.{rule}` keys must resolve identically
        // across the rector's GROUP-set shape flip (flat rules ->
        // FluentRule::array()->each(...)). If the grouping rector
        // re-keys attribute paths, wildcard-keyed custom messages
        // stop resolving and the assertSame fails.

        yield 'flat custom message — email.required' => [
            'data' => ['email' => ''],
            'stringRules' => ['email' => 'required|email'],
            'fluentRules' => ['email' => FluentRule::email()->required()],
            'attributeNames' => [],
            'customMessages' => ['email.required' => 'Provide your email.'],
            'expectedMessagesContain' => 'Provide your email.',
        ];

        yield 'flat custom message — multiple rules on one attribute' => [
            'data' => ['email' => 'bad'],
            'stringRules' => ['email' => 'required|email|max:5'],
            'fluentRules' => ['email' => FluentRule::email()->required()->max(5)],
            'attributeNames' => [],
            'customMessages' => [
                'email.email' => 'Must be a real email.',
                'email.max' => 'Too long.',
            ],
            'expectedMessagesContain' => 'Must be a real email.',
        ];

        yield 'flat custom message + attribute rename — both apply' => [
            'data' => ['email' => ''],
            'stringRules' => ['email' => 'required|email'],
            'fluentRules' => ['email' => FluentRule::email()->required()],
            'attributeNames' => ['email' => 'E-mail address'],
            'customMessages' => ['email.required' => 'The :attribute is mandatory.'],
            'expectedMessagesContain' => 'The E-mail address is mandatory.',
        ];

        yield 'wildcard custom message — items.*.name.required via each()' => [
            'data' => ['items' => [['name' => ''], ['name' => 'ok'], ['name' => '']]],
            'stringRules' => [
                'items' => 'required|array',
                'items.*.name' => 'required|string',
            ],
            'fluentRules' => [
                'items' => FluentRule::array()->required()->each([
                    'name' => FluentRule::string()->required(),
                ]),
            ],
            'attributeNames' => [],
            'customMessages' => ['items.*.name.required' => 'Each item needs a name.'],
            'expectedMessagesContain' => 'Each item needs a name.',
            'langLines' => [],
            'expectedErrorKeys' => ['items.0.name', 'items.2.name'],
            'expectedMessageCount' => 2,
        ];

        yield 'wildcard custom message — items.*.rule on scalar each()' => [
            'data' => ['items' => ['ok', 123, str_repeat('x', 60)]],
            'stringRules' => [
                'items' => 'required|array',
                'items.*' => 'string|max:50',
            ],
            'fluentRules' => [
                'items' => FluentRule::array()->required()->each(FluentRule::string()->max(50)),
            ],
            'attributeNames' => [],
            'customMessages' => [
                'items.*.string' => 'Each item must be a string.',
                'items.*.max' => 'Each item capped at :max chars.',
            ],
            'expectedMessagesContain' => 'Each item must be a string.',
            'langLines' => [],
            'expectedErrorKeys' => ['items.1', 'items.2'],
            'expectedMessageCount' => 2,
        ];

        // --- Phase 3: lang-file override parity ---
        // Registered in-memory via `translator->addLines(...)`. Keys use
        // the full dot-path into the `validation` group so they resolve
        // through the translator the same way a published
        // `lang/en/validation.php` file would.

        yield 'lang override — validation.custom.email.required' => [
            'data' => ['email' => ''],
            'stringRules' => ['email' => 'required|email'],
            'fluentRules' => ['email' => FluentRule::email()->required()],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => 'Lang-file wants your email.',
            'langLines' => [
                'validation.custom.email.required' => 'Lang-file wants your email.',
            ],
        ];

        yield 'lang override — validation.attributes.email rename' => [
            'data' => ['email' => ''],
            'stringRules' => ['email' => 'required|email'],
            'fluentRules' => ['email' => FluentRule::email()->required()],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => 'E-mail address',
            'langLines' => [
                'validation.attributes.email' => 'E-mail address',
            ],
        ];

        yield 'lang override — custom.* + attributes.* interact via :attribute' => [
            'data' => ['email' => ''],
            'stringRules' => ['email' => 'required|email'],
            'fluentRules' => ['email' => FluentRule::email()->required()],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => 'The E-mail address is mandatory.',
            'langLines' => [
                'validation.custom.email.required' => 'The :attribute is mandatory.',
                'validation.attributes.email' => 'E-mail address',
            ],
        ];

        yield 'lang override — wildcard custom.items.*.name.required via each()' => [
            'data' => ['items' => [['name' => ''], ['name' => 'ok'], ['name' => '']]],
            'stringRules' => [
                'items' => 'required|array',
                'items.*.name' => 'required|string',
            ],
            'fluentRules' => [
                'items' => FluentRule::array()->required()->each([
                    'name' => FluentRule::string()->required(),
                ]),
            ],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => 'Each item needs a name via lang.',
            'langLines' => [
                'validation.custom.items.*.name.required' => 'Each item needs a name via lang.',
            ],
        ];

        // --- Phase 4: deep-nesting / multi-sibling / mixed-key bag parity ---
        // Attribute-bag key shape (no .{rule} suffix) must match across
        // the GROUP-set shape flip at every nesting depth. The
        // collapse-wildcard-to-index logic is framework-internal — if it
        // shifts in a Laravel upgrade, these rows surface the drift.

        yield 'deep wildcard — users.*.tags.* nested each()' => [
            'data' => ['users' => [
                ['tags' => ['ok', '']],
                ['tags' => ['']],
            ]],
            'stringRules' => [
                'users' => 'required|array',
                'users.*.tags' => 'required|array',
                'users.*.tags.*' => 'required|string',
            ],
            'fluentRules' => [
                'users' => FluentRule::array()->required()->each([
                    'tags' => FluentRule::array()->required()->each(
                        FluentRule::string()->required(),
                    ),
                ]),
            ],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => null,
            'langLines' => [],
            'expectedErrorKeys' => ['users.0.tags.1', 'users.1.tags.0'],
            'expectedMessageCount' => 2,
        ];

        yield 'multi-sibling wildcards — items.*.name + items.*.email at same depth' => [
            'data' => ['items' => [
                ['name' => '', 'email' => 'bad'],
                ['name' => 'ok', 'email' => 'ok@example.com'],
                ['name' => '', 'email' => 'still-bad'],
            ]],
            'stringRules' => [
                'items' => 'required|array',
                'items.*.name' => 'required|string',
                'items.*.email' => 'required|email',
            ],
            'fluentRules' => [
                'items' => FluentRule::array()->required()->each([
                    'name' => FluentRule::string()->required(),
                    'email' => FluentRule::email()->required(),
                ]),
            ],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => null,
            'langLines' => [],
            'expectedErrorKeys' => ['items.0.name', 'items.2.name', 'items.0.email', 'items.2.email'],
            'expectedMessageCount' => 4,
        ];

        yield 'mixed keyed + wildcard — users.*.name wildcard with users.0.admin keyed' => [
            'data' => ['users' => [
                ['name' => '', 'admin' => 'not-a-bool'],
                ['name' => ''],
            ]],
            'stringRules' => [
                'users' => 'required|array',
                'users.0.admin' => 'required|boolean',
                'users.*.name' => 'required|string',
            ],
            'fluentRules' => [
                'users.0.admin' => FluentRule::boolean()->required(),
                'users' => FluentRule::array()->required()->each([
                    'name' => FluentRule::string()->required(),
                ]),
            ],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => null,
            'langLines' => [],
            'expectedErrorKeys' => ['users.0.admin', 'users.0.name', 'users.1.name'],
            'expectedMessageCount' => 3,
        ];

        // --- Coverage: additional rule-family branches ---
        // Distinct rendering / lookup paths not exercised by the rows
        // above. `confirmed` renders with a sibling-lookup pattern
        // (`{attr}_confirmation`) and the custom-message key is
        // `{attr}.confirmed`, not `{attr}_confirmation.confirmed`.
        // `required_if` / `required_unless` are a separate conditional
        // branch from `required_with` / `required_without` (different
        // evaluator path in Laravel's validator).

        yield 'required_if — dependent field triggers requirement' => [
            'data' => ['type' => 'admin'],
            'stringRules' => [
                'type' => 'required|string',
                'password' => 'required_if:type,admin|string',
            ],
            'fluentRules' => [
                'type' => FluentRule::string()->required(),
                'password' => FluentRule::string()->requiredIf('type', 'admin'),
            ],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => null,
            'langLines' => [],
            'expectedErrorKeys' => ['password'],
            'expectedMessageCount' => 1,
        ];

        yield 'required_if — dependent field absent (no error)' => [
            'data' => ['type' => 'guest'],
            'stringRules' => [
                'type' => 'required|string',
                'password' => 'required_if:type,admin|string',
            ],
            'fluentRules' => [
                'type' => FluentRule::string()->required(),
                'password' => FluentRule::string()->requiredIf('type', 'admin'),
            ],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => null,
            'langLines' => [],
            'expectedErrorKeys' => [],
            'expectedMessageCount' => 0,
        ];

        yield 'required_unless — inverted conditional triggers' => [
            'data' => ['plan' => 'free'],
            'stringRules' => [
                'plan' => 'required|string',
                'card_number' => 'required_unless:plan,free|string',
            ],
            'fluentRules' => [
                'plan' => FluentRule::string()->required(),
                'card_number' => FluentRule::string()->requiredUnless('plan', 'free'),
            ],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => null,
            'langLines' => [],
            'expectedErrorKeys' => [],
            'expectedMessageCount' => 0,
        ];

        yield 'required_if + custom message via {attr}.required_if key' => [
            'data' => ['type' => 'admin'],
            'stringRules' => [
                'type' => 'required|string',
                'password' => 'required_if:type,admin|string',
            ],
            'fluentRules' => [
                'type' => FluentRule::string()->required(),
                'password' => FluentRule::string()->requiredIf('type', 'admin'),
            ],
            'attributeNames' => [],
            'customMessages' => ['password.required_if' => 'Admins must set a password.'],
            'expectedMessagesContain' => 'Admins must set a password.',
            'langLines' => [],
            'expectedErrorKeys' => ['password'],
            'expectedMessageCount' => 1,
        ];

        yield 'confirmed — mismatched confirmation value' => [
            'data' => ['password' => 'secret', 'password_confirmation' => 'different'],
            'stringRules' => ['password' => 'required|confirmed'],
            'fluentRules' => ['password' => FluentRule::string()->required()->confirmed()],
            'attributeNames' => [],
            'customMessages' => [],
            'expectedMessagesContain' => null,
            'langLines' => [],
            'expectedErrorKeys' => ['password'],
            'expectedMessageCount' => 1,
        ];

        yield 'confirmed + custom message — {attr}.confirmed lookup' => [
            'data' => ['password' => 'secret', 'password_confirmation' => 'different'],
            'stringRules' => ['password' => 'required|confirmed'],
            'fluentRules' => ['password' => FluentRule::string()->required()->confirmed()],
            'attributeNames' => [],
            'customMessages' => ['password.confirmed' => 'Passwords must match.'],
            'expectedMessagesContain' => 'Passwords must match.',
            'langLines' => [],
            'expectedErrorKeys' => ['password'],
            'expectedMessageCount' => 1,
        ];

        yield 'deep wildcard + custom-message lookup — users.*.tags.*.required' => [
            'data' => ['users' => [
                ['tags' => ['', 'ok']],
                ['tags' => ['']],
            ]],
            'stringRules' => [
                'users' => 'required|array',
                'users.*.tags' => 'required|array',
                'users.*.tags.*' => 'required|string',
            ],
            'fluentRules' => [
                'users' => FluentRule::array()->required()->each([
                    'tags' => FluentRule::array()->required()->each(
                        FluentRule::string()->required(),
                    ),
                ]),
            ],
            'attributeNames' => [],
            'customMessages' => [
                'users.*.tags.*.required' => 'Every tag must be present.',
            ],
            'expectedMessagesContain' => 'Every tag must be present.',
            'langLines' => [],
            'expectedErrorKeys' => ['users.0.tags.0', 'users.1.tags.0'],
            'expectedMessageCount' => 2,
        ];
    }
}
