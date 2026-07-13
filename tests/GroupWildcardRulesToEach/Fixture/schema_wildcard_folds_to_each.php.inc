<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;

class SchemaWildcardFoldsToEachRequest extends FormRequest
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()->required(),
            'items.*.title' => $rules->string()->required(),
            'items.*.qty' => $rules->numeric()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;

class SchemaWildcardFoldsToEachRequest extends FormRequest
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()->required(),
            'items' => $rules->array()
                ->each([
                'title' => $rules->string()->required(),
                'qty' => $rules->numeric()->required(),
            ]),
        ];
    }
}

?>
