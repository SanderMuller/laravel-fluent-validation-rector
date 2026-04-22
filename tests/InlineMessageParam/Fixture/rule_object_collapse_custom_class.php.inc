<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RuleObjectCollapseCustomClass
{
    public function rules(): array
    {
        // Custom user-defined rule class — basename `MyCustomRule` →
        // lcfirst → 'myCustomRule' per the HasFieldModifiers default case.
        return [
            'field' => FluentRule::string()->rule(new MyCustomRule())->messageFor('myCustomRule', 'Custom failed.'),
        ];
    }
}

class MyCustomRule
{
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RuleObjectCollapseCustomClass
{
    public function rules(): array
    {
        // Custom user-defined rule class — basename `MyCustomRule` →
        // lcfirst → 'myCustomRule' per the HasFieldModifiers default case.
        return [
            'field' => FluentRule::string()->rule(new MyCustomRule(), message: 'Custom failed.'),
        ];
    }
}

class MyCustomRule
{
}

?>
