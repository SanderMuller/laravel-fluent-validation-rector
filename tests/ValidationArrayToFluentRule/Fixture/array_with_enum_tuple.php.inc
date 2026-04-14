<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

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

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use App\Enums\InteractionType;
use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;

class EnumTupleFormRequest extends FormRequest
{
    private const FIELD = 'type';

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->requiredIf(self::FIELD, InteractionType::PAUSE->value, InteractionType::FRAME->value),
            'status' => FluentRule::string()->excludeUnless('type', Status::ACTIVE->value)->nullable(),
        ];
    }
}

?>
