<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseRegexWithPattern
{
    public function rules(): array
    {
        return [
            'slug' => FluentRule::regex('/^[a-z0-9-]+$/')->message('Bad slug.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseRegexWithPattern
{
    public function rules(): array
    {
        return [
            'slug' => FluentRule::regex('/^[a-z0-9-]+$/', message: 'Bad slug.'),
        ];
    }
}

?>
