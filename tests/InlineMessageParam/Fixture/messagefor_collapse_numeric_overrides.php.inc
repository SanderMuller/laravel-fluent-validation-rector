<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class MessageForCollapseNumericOverrides
{
    public function rules(): array
    {
        return [
            // NumericRule emitted-key overrides (see EMITTED_KEY_OVERRIDES table).
            'min_refs' => FluentRule::numeric()->greaterThan('floor')->messageFor('gt', 'Must exceed floor.'),
            'max_refs' => FluentRule::numeric()->lessThan('ceiling')->messageFor('lt', 'Must be below ceiling.'),
            'pos' => FluentRule::numeric()->positive()->messageFor('gt', 'Must be positive.'),
            'non_neg' => FluentRule::numeric()->nonNegative()->messageFor('gte', 'Must be zero or positive.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class MessageForCollapseNumericOverrides
{
    public function rules(): array
    {
        return [
            // NumericRule emitted-key overrides (see EMITTED_KEY_OVERRIDES table).
            'min_refs' => FluentRule::numeric()->greaterThan('floor', message: 'Must exceed floor.'),
            'max_refs' => FluentRule::numeric()->lessThan('ceiling', message: 'Must be below ceiling.'),
            'pos' => FluentRule::numeric()->positive(message: 'Must be positive.'),
            'non_neg' => FluentRule::numeric()->nonNegative(message: 'Must be zero or positive.'),
        ];
    }
}

?>
