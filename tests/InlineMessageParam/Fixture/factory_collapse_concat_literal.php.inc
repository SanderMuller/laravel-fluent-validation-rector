<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseConcatLiteral
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()->message('Please provide ' . 'a valid email.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseConcatLiteral
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email(message: 'Please provide ' . 'a valid email.'),
        ];
    }
}

?>
