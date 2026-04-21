<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class InRuleFacadeStringFactory
{
    public function rules(): array
    {
        return [
            'role' => FluentRule::string()->rule(Rule::in(['admin', 'user'])),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class InRuleFacadeStringFactory
{
    public function rules(): array
    {
        return [
            'role' => FluentRule::string()->in(['admin', 'user']),
        ];
    }
}

?>
