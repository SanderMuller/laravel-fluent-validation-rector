<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// FluentRule::integer() seeds 'numeric' then 'integer', the same pair in the same order.
class PromoteFieldNumericIntegerTokensToInteger
{
    public function rules(): array
    {
        return [
            'ids.*' => FluentRule::field()->bail()->required()->rule('numeric')->rule('integer'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// FluentRule::integer() seeds 'numeric' then 'integer', the same pair in the same order.
class PromoteFieldNumericIntegerTokensToInteger
{
    public function rules(): array
    {
        return [
            'ids.*' => FluentRule::integer()->bail()->required(),
        ];
    }
}

?>
