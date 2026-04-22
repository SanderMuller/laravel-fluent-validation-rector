<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SignHelpersOnNumeric
{
    public function rules(): array
    {
        // 1.19.0 sign helpers — literal-zero comparisons map to the
        // four sign-helper methods on NumericRule. Result is a zero-arg
        // call (->positive(), not ->positive(0)). Non-zero literals
        // and field-name comparisons stay as escape hatch (covered by
        // the paired skip_sign_helpers_non_zero_or_field.php.inc).
        return [
            'pos' => FluentRule::numeric()->rule('gt:0'),
            'nn' => FluentRule::numeric()->rule('gte:0'),
            'neg' => FluentRule::numeric()->rule('lt:0'),
            'np' => FluentRule::numeric()->rule('lte:0'),
            // Float spelling also accepted.
            'pos_f' => FluentRule::numeric()->rule('gt:0.0'),
            // Array form.
            'arr' => FluentRule::numeric()->rule(['gte', 0]),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SignHelpersOnNumeric
{
    public function rules(): array
    {
        // 1.19.0 sign helpers — literal-zero comparisons map to the
        // four sign-helper methods on NumericRule. Result is a zero-arg
        // call (->positive(), not ->positive(0)). Non-zero literals
        // and field-name comparisons stay as escape hatch (covered by
        // the paired skip_sign_helpers_non_zero_or_field.php.inc).
        return [
            'pos' => FluentRule::numeric()->positive(),
            'nn' => FluentRule::numeric()->nonNegative(),
            'neg' => FluentRule::numeric()->negative(),
            'np' => FluentRule::numeric()->nonPositive(),
            // Float spelling also accepted.
            'pos_f' => FluentRule::numeric()->positive(),
            // Array form.
            'arr' => FluentRule::numeric()->nonNegative(),
        ];
    }
}

?>
