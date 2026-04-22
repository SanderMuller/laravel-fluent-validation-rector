<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseStringWithChain
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->message('Name is required.')->required()->max(255),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseStringWithChain
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string(message: 'Name is required.')->required()->max(255),
        ];
    }
}

?>
