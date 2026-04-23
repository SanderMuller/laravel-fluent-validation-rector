<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class EnumValueArgTupleFormRequest extends FormRequest
{
    private const FIELD = 'type';

    public function rules(): array
    {
        return [
            'a' => [
                ['exclude_unless', self::FIELD, \App\Enums\InteractionType::PAUSE->value],
                'sometimes',
                'string',
            ],
            'b' => [
                ['required_if', self::FIELD, \App\Enums\InteractionType::FRAME->value],
                'string',
            ],
            'c' => [
                ['required_if', self::FIELD, \App\Enums\InteractionType::PAUSE, \App\Enums\InteractionType::FRAME->value],
                'string',
            ],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use App\Enums\InteractionType;
use Illuminate\Foundation\Http\FormRequest;

class EnumValueArgTupleFormRequest extends FormRequest
{
    private const FIELD = 'type';

    public function rules(): array
    {
        return [
            'a' => FluentRule::string()->excludeUnless(self::FIELD, InteractionType::PAUSE->value)->sometimes(),
            'b' => FluentRule::string()->requiredIf(self::FIELD, InteractionType::FRAME->value),
            'c' => FluentRule::string()->requiredIf(self::FIELD, InteractionType::PAUSE->value, InteractionType::FRAME->value),
        ];
    }
}

?>
