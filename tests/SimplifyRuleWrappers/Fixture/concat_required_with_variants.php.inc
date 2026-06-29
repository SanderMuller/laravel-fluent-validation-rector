<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class ConcatRequiredWithVariants
{
    private const string SUFFIX = 'date';

    public function rules(): array
    {
        $field = 'sibling';

        return [
            // Non-empty remainder after the colon — the leading literal's
            // post-colon text becomes the left-most operand of the field expr.
            'a' => FluentRule::string()->rule('required_with:end_' . self::SUFFIX),
            // Variable operand.
            'b' => FluentRule::string()->rule('required_without:' . $field),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class ConcatRequiredWithVariants
{
    private const string SUFFIX = 'date';

    public function rules(): array
    {
        $field = 'sibling';

        return [
            // Non-empty remainder after the colon — the leading literal's
            // post-colon text becomes the left-most operand of the field expr.
            'a' => FluentRule::string()
                ->requiredWith('end_' . self::SUFFIX),
            // Variable operand.
            'b' => FluentRule::string()
                ->requiredWithout($field),
        ];
    }
}

?>
