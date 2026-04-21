<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RegexStringFormStringFactory
{
    public function rules(): array
    {
        return [
            'code' => FluentRule::string()->rule('regex:/^\d+$/'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RegexStringFormStringFactory
{
    public function rules(): array
    {
        return [
            'code' => FluentRule::string()->regex('/^\d+$/'),
        ];
    }
}

?>
