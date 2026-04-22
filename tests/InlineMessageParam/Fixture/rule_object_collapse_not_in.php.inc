<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use Illuminate\Validation\Rules\NotIn;
use SanderMuller\FluentValidation\FluentRule;

class RuleObjectCollapseNotIn
{
    public function rules(): array
    {
        return [
            'status' => FluentRule::string()->rule(new NotIn(['banned', 'deleted']))->messageFor('not_in', 'That status is reserved.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use Illuminate\Validation\Rules\NotIn;
use SanderMuller\FluentValidation\FluentRule;

class RuleObjectCollapseNotIn
{
    public function rules(): array
    {
        return [
            'status' => FluentRule::string()->rule(new NotIn(['banned', 'deleted']), message: 'That status is reserved.'),
        ];
    }
}

?>
