<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentValidator;

// Validator subclass extending FluentValidator with `#[FluentRules]`
// on a non-`rules()` helper. The attribute qualifies the class
// (per-method scoping) and the converter walks the attributed body.
class JsonImportValidator extends FluentValidator
{
    #[FluentRules]
    protected function rulesWithoutPrefix(): array
    {
        return [
            'title' => 'required|string',
            'lang' => 'required|in:en,nl,de',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentValidator;

// Validator subclass extending FluentValidator with `#[FluentRules]`
// on a non-`rules()` helper. The attribute qualifies the class
// (per-method scoping) and the converter walks the attributed body.
class JsonImportValidator extends FluentValidator
{
    #[FluentRules]
    protected function rulesWithoutPrefix(): array
    {
        return [
            'title' => FluentRule::string()->required(),
            'lang' => FluentRule::field()->required()->rule('in:en,nl,de'),
        ];
    }
}

?>
