<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseEmail
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()->message('Bad email.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseEmail
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email(message: 'Bad email.'),
        ];
    }
}

?>
