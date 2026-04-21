<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class MinMaxStringFormNumeric
{
    public function rules(): array
    {
        return [
            'age' => FluentRule::numeric()->rule('min:0'),
            'count' => FluentRule::numeric()->rule('max:100'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class MinMaxStringFormNumeric
{
    public function rules(): array
    {
        return [
            'age' => FluentRule::numeric()->min(0),
            'count' => FluentRule::numeric()->max(100),
        ];
    }
}

?>
