<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use Illuminate\Validation\Rules\RequiredIf;
use SanderMuller\FluentValidation\FluentRule;

class RuleObjectCollapseRequiredIf
{
    public function rules(): array
    {
        // RequiredIf/RequiredUnless both emit key 'required' per the
        // HasFieldModifiers::addRule match statement.
        return [
            'password' => FluentRule::string()->rule(new RequiredIf(true))->messageFor('required', 'Password needed.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use Illuminate\Validation\Rules\RequiredIf;
use SanderMuller\FluentValidation\FluentRule;

class RuleObjectCollapseRequiredIf
{
    public function rules(): array
    {
        // RequiredIf/RequiredUnless both emit key 'required' per the
        // HasFieldModifiers::addRule match statement.
        return [
            'password' => FluentRule::string()->rule(new RequiredIf(true), message: 'Password needed.'),
        ];
    }
}

?>
