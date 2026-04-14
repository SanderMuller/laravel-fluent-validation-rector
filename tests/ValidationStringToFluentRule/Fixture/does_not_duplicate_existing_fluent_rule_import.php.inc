<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class PreImportedFluentRuleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'manual' => FluentRule::string()->required(),
            'auto' => 'required|string|max:100',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class PreImportedFluentRuleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'manual' => FluentRule::string()->required(),
            'auto' => FluentRule::string()->required()->max(100),
        ];
    }
}

?>
