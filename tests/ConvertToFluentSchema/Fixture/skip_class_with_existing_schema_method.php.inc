<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

// Class already declares a schema() method — renaming rules() would produce a
// duplicate method declaration. Leave both methods as-is.
class SkipClassWithExistingSchemaMethod extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }

    public function schema(FluentSchema $rules): array
    {
        return [
            'email' => $rules->email()->required(),
        ];
    }
}

?>
