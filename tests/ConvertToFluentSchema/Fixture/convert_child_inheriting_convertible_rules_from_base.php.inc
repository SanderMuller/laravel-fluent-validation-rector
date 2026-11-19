<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ConcreteBaseRequest;

// The base converts to schema() as well, so after both renames
// $request->rules() resolves to the HasFluentRules fallback again.
class ChildInheritingConvertibleRulesRequest extends ConcreteBaseRequest
{
    public function rules(): array
    {
        return [
            'body' => FluentRule::string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Source\ConcreteBaseRequest;

// The base converts to schema() as well, so after both renames
// $request->rules() resolves to the HasFluentRules fallback again.
class ChildInheritingConvertibleRulesRequest extends ConcreteBaseRequest
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'body' => $rules->string()->required(),
        ];
    }
}

?>
