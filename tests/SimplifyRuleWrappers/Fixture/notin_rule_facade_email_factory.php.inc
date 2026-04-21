<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class NotInRuleFacadeEmailFactory
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()->rule(Rule::notIn(['banned@example.com'])),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class NotInRuleFacadeEmailFactory
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()->notIn(['banned@example.com']),
        ];
    }
}

?>
