<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class ConditionalSpreadFormRequest extends FormRequest
{
    private const FIELD = 'type';

    public function rules(): array
    {
        $dynamicValues = [1, 2, 3];
        $fields = ['a', 'b'];

        return [
            'a' => [
                ['required_unless', self::FIELD, ...\App\Enums\InteractionType::list()],
                'numeric',
            ],
            'b' => [
                ['required_if', self::FIELD, ...$dynamicValues],
                'string',
            ],
            'c' => [
                ['exclude_unless', self::FIELD, 'literal', ...\App\Enums\InteractionType::list()],
                'nullable',
                'string',
            ],
            'd' => [
                ['required_with', ...$fields],
                'string',
            ],
            'e' => [
                ['required_unless', self::FIELD, ...\App\Enums\InteractionType::VALUES],
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

class ConditionalSpreadFormRequest extends FormRequest
{
    private const FIELD = 'type';

    public function rules(): array
    {
        $dynamicValues = [1, 2, 3];
        $fields = ['a', 'b'];

        return [
            'a' => FluentRule::numeric()->requiredUnless(self::FIELD, ...InteractionType::list()),
            'b' => FluentRule::string()->requiredIf(self::FIELD, ...$dynamicValues),
            'c' => FluentRule::string()->excludeUnless(self::FIELD, 'literal', ...InteractionType::list())->nullable(),
            'd' => FluentRule::string()->requiredWith(...$fields),
            'e' => FluentRule::string()->requiredUnless(self::FIELD, ...InteractionType::VALUES),
        ];
    }
}

?>
