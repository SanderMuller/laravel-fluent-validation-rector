<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use Illuminate\Validation\Rules\In;
use SanderMuller\FluentValidation\FluentRule;

class RuleObjectCollapseIn
{
    public function rules(): array
    {
        return [
            'role' => FluentRule::string()->rule(new In(['admin', 'user']))->messageFor('in', 'Pick a valid role.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use Illuminate\Validation\Rules\In;
use SanderMuller\FluentValidation\FluentRule;

class RuleObjectCollapseIn
{
    public function rules(): array
    {
        return [
            'role' => FluentRule::string()->rule(new In(['admin', 'user']), message: 'Pick a valid role.'),
        ];
    }
}

?>
