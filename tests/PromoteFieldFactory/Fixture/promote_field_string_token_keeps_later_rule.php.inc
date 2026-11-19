<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Rule hops after the type token keep their order, so only the token hop is removed.
class PromoteFieldStringTokenKeepsLaterRule
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::field()->required()->rule('string')->rule('max:5'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Rule hops after the type token keep their order, so only the token hop is removed.
class PromoteFieldStringTokenKeepsLaterRule
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required()->rule('max:5'),
        ];
    }
}

?>
