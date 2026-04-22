<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class MessageForCollapseStringMin
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->min(3)->messageFor('min', 'Too short.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class MessageForCollapseStringMin
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->min(3, message: 'Too short.'),
        ];
    }
}

?>
