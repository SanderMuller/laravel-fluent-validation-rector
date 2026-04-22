<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseNumeric
{
    public function rules(): array
    {
        return [
            'age' => FluentRule::numeric()->message('Age required.')->min(0),
            'count' => FluentRule::integer()->message('Must be integer.')->max(100),
            'active' => FluentRule::boolean()->message('Yes or no.'),
            'rows' => FluentRule::array()->message('Need rows.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseNumeric
{
    public function rules(): array
    {
        return [
            'age' => FluentRule::numeric(message: 'Age required.')->min(0),
            'count' => FluentRule::integer(message: 'Must be integer.')->max(100),
            'active' => FluentRule::boolean(message: 'Yes or no.'),
            'rows' => FluentRule::array(message: 'Need rows.'),
        ];
    }
}

?>
