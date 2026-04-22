<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class MessageForCollapseOnFactory
{
    public function rules(): array
    {
        // `FluentRule::email()->messageFor('email', 'msg')` — key matches
        // factory name, factory_rewritable, so collapse to factory-level
        // inline message:.
        return [
            'email' => FluentRule::email()->messageFor('email', 'Bad email.'),
            'id' => FluentRule::uuid()->messageFor('uuid', 'Bad UUID.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class MessageForCollapseOnFactory
{
    public function rules(): array
    {
        // `FluentRule::email()->messageFor('email', 'msg')` — key matches
        // factory name, factory_rewritable, so collapse to factory-level
        // inline message:.
        return [
            'email' => FluentRule::email(message: 'Bad email.'),
            'id' => FluentRule::uuid(message: 'Bad UUID.'),
        ];
    }
}

?>
