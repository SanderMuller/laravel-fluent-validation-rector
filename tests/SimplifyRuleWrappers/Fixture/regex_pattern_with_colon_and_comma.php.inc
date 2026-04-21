<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RegexPatternWithColonAndComma
{
    public function rules(): array
    {
        // Regression pin: pattern itself contains `:` and `,` — must NOT
        // be split as if it were a `between` or shape with multiple args.
        // Splitter must use first `:` only and never split tail on `,`.
        return [
            'time' => FluentRule::string()->rule('regex:/^\d+:\d+$/'),
            'list' => FluentRule::string()->rule('regex:/^a,b,c$/'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RegexPatternWithColonAndComma
{
    public function rules(): array
    {
        // Regression pin: pattern itself contains `:` and `,` — must NOT
        // be split as if it were a `between` or shape with multiple args.
        // Splitter must use first `:` only and never split tail on `,`.
        return [
            'time' => FluentRule::string()->regex('/^\d+:\d+$/'),
            'list' => FluentRule::string()->regex('/^a,b,c$/'),
        ];
    }
}

?>
