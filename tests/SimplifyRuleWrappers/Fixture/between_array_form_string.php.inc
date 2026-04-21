<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class BetweenArrayFormString
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->rule(['between', 2, 255]),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class BetweenArrayFormString
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->between(2, 255),
        ];
    }
}

?>
