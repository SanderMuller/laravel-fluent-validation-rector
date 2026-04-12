<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentValidator;

class JsonImportValidator extends FluentValidator
{
    #[FluentRules]
    public function rulesWithoutPrefix(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentValidator;

class JsonImportValidator extends FluentValidator
{
    #[FluentRules]
    public function rulesWithoutPrefix(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
            'email' => FluentRule::email()->required(),
        ];
    }
}

?>
