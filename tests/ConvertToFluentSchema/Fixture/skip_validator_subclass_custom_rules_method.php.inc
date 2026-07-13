<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentValidator;

// A FluentValidator subclass builds rules through a custom-named method and has
// no rules()/schema() method and no HasFluentRules trait. The rule converts
// only a literal rules() on a HasFluentRules user, so this is left untouched —
// its parent post-processes the returned array, which a rename would break.
class CustomFieldValidator extends FluentValidator
{
    protected function rulesWithoutPrefix(): array
    {
        return [
            'items.*.name' => FluentRule::string()->required(),
        ];
    }
}

?>
