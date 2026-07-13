<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

class NarrowSchemaWithNestedEach extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $rules): array
    {
        return [
            'items' => $rules->array()->required()->each([
                'name' => $rules->string()->required(),
            ]),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

class NarrowSchemaWithNestedEach extends FormRequest
{
    use HasFluentRules;

    /**
     * @return array<string, FluentRuleContract>
     */
    public function schema(FluentSchema $rules): array
    {
        return [
            'items' => $rules->array()->required()->each([
                'name' => $rules->string()->required(),
            ]),
        ];
    }
}

?>
