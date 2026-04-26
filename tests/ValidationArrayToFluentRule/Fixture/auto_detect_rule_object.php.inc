<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutoDetectRuleObjectInHelper extends FormRequest
{
    public function rules(): array
    {
        return $this->shippingRules();
    }

    private function shippingRules(): array
    {
        return [
            'address' => ['required', 'string'],
            'role' => [Rule::in(['admin', 'editor'])],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutoDetectRuleObjectInHelper extends FormRequest
{
    public function rules(): array
    {
        return $this->shippingRules();
    }

    private function shippingRules(): array
    {
        return [
            'address' => FluentRule::string()->required(),
            'role' => FluentRule::field()->in(['admin', 'editor']),
        ];
    }
}

?>
