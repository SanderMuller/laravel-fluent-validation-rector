<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class ConvertWithArrowFunctionConditional extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'nickname' => FluentRule::string()->requiredIf(fn () => $this->has('flag')),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

class ConvertWithArrowFunctionConditional extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $rules): array
    {
        return [
            'nickname' => $rules->string()->requiredIf(fn () => $this->has('flag')),
        ];
    }
}

?>
