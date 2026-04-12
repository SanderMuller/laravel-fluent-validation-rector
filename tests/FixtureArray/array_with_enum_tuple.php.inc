<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;

class EnumTupleFormRequest extends FormRequest
{
    private const FIELD = 'type';

    public function rules(): array
    {
        return [
            'name' => [
                ['required_if', self::FIELD, \App\Enums\InteractionType::PAUSE, \App\Enums\InteractionType::FRAME],
                'string',
            ],
            'status' => [
                ['exclude_unless', 'type', \App\Enums\Status::ACTIVE],
                'nullable',
                'string',
            ],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class EnumTupleFormRequest extends FormRequest
{
    private const FIELD = 'type';

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->requiredIf(self::FIELD, \App\Enums\InteractionType::PAUSE->value, \App\Enums\InteractionType::FRAME->value),
            'status' => FluentRule::string()->excludeUnless('type', \App\Enums\Status::ACTIVE->value)->nullable(),
        ];
    }
}

?>
