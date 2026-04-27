<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentValidator;

// Parent-safety wins over the attribute. `JsonChild` does
// `array_merge(parent::rulesWithoutPrefix(), [...])`, so the parent
// is marked unsafe. The attribute is the user's claim about THEIR
// method's audit-safety, NOT a license to override cross-class
// parent-safety. The attributed parent method is NOT rewritten.
class JsonParent extends FluentValidator
{
    #[FluentRules]
    public function rulesWithoutPrefix(): array
    {
        return [
            'title' => 'required|string',
        ];
    }
}

class JsonChild extends JsonParent
{
    public function rulesWithoutPrefix(): array
    {
        return array_merge(parent::rulesWithoutPrefix(), [
            'extra' => 'required|string',
        ]);
    }
}

?>
