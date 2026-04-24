<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class PromoteToStringViaRegexSingleton
{
    public function rules(): array
    {
        return [
            // `between` is a known v1 rewrite target. It exists on StringRule,
            // NumericRule, ArrayRule, FileRule — no promotion from this alone.
            // Adding `not_in` (NumericRule via int args list — actually on many
            // typed builders) — need a rule that's truly string/numeric-only.
            // Use `regex` (StringRule only) to force a singleton intersection.
            'age_label' => FluentRule::field()->rule('between:1,100')->rule('regex:/^\d+$/'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class PromoteToStringViaRegexSingleton
{
    public function rules(): array
    {
        return [
            // `between` is a known v1 rewrite target. It exists on StringRule,
            // NumericRule, ArrayRule, FileRule — no promotion from this alone.
            // Adding `not_in` (NumericRule via int args list — actually on many
            // typed builders) — need a rule that's truly string/numeric-only.
            // Use `regex` (StringRule only) to force a singleton intersection.
            'age_label' => FluentRule::string()->rule('between:1,100')->rule('regex:/^\d+$/'),
        ];
    }
}

?>
