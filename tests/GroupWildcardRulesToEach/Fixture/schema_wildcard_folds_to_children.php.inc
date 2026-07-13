<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;

class SchemaWildcardFoldsToChildrenRequest extends FormRequest
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'id' => $rules->integer()->required(),
            '*.label' => $rules->string()->required(),
            '*.value' => $rules->string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;

class SchemaWildcardFoldsToChildrenRequest extends FormRequest
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'id' => $rules->integer()->required(),
            '*' => $rules->array()
                ->children([
                'label' => $rules->string()->required(),
                'value' => $rules->string()->required(),
            ]),
        ];
    }
}

?>
