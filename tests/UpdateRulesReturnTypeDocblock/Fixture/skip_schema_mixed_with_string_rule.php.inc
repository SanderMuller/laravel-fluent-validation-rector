<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

// One value is still a raw string rule, so the array isn't uniformly
// FluentRuleContract — narrowing the @return would be a type-lie. Leave it.
class SkipSchemaMixedWithStringRule extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $rules): array
    {
        return [
            'title' => $rules->string()->required(),
            'raw' => 'required|string',
        ];
    }
}

?>
