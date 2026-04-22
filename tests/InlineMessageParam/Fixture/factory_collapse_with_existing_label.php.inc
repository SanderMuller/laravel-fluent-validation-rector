<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseWithExistingLabel
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email('E-mail address')->message('Bad email.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseWithExistingLabel
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email('E-mail address', message: 'Bad email.'),
        ];
    }
}

?>
