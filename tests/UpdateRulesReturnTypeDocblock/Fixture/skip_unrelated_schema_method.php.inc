<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\HasFluentRules;

// A schema() whose first parameter is NOT FluentSchema is an unrelated method
// (e.g. a JSON/DB schema builder), not the FluentSchema hook — leave it alone.
class SkipUnrelatedSchemaMethod extends FormRequest
{
    use HasFluentRules;

    public function schema(array $config): array
    {
        return [
            'label' => 'value',
        ];
    }
}

?>
