<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RegexArrayForm
{
    public function rules(): array
    {
        return [
            'slug' => FluentRule::string()->rule(['regex', '/^[a-z0-9-]+$/']),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class RegexArrayForm
{
    public function rules(): array
    {
        return [
            'slug' => FluentRule::string()->regex('/^[a-z0-9-]+$/'),
        ];
    }
}

?>
