<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class InStringFormNumericFactory
{
    public function rules(): array
    {
        return [
            'level' => FluentRule::numeric()->rule('in:1,2,3'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class InStringFormNumericFactory
{
    public function rules(): array
    {
        return [
            'level' => FluentRule::numeric()->in(['1', '2', '3']),
        ];
    }
}

?>
