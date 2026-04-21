<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class InArrayFormFieldFactory
{
    public function rules(): array
    {
        return [
            'status' => FluentRule::field()->rule(['in', 'active', 'inactive']),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class InArrayFormFieldFactory
{
    public function rules(): array
    {
        return [
            'status' => FluentRule::field()->in(['active', 'inactive']),
        ];
    }
}

?>
