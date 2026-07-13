<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

// A schema(FluentSchema) method on a non-FormRequest class: HasFluentRules
// overrides a FormRequest-internal hook, so adding it here would be dead code.
// The FormRequest-ancestry gate keeps the trait off.
class PlainSchemaHolder
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'title' => $rules->string()->required(),
        ];
    }
}

?>
