<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class ConditionalTupleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                ['required_if', 'role', 'admin'],
                'string',
                'max:255',
            ],
            'notes' => [
                ['exclude_unless', 'status', 'active'],
                'nullable',
                'string',
            ],
            'phone' => [
                ['required_with', 'email'],
                'string',
            ],
            'dynamic_max' => [
                'required',
                ['max', $this->getMaxValue()],
            ],
            'safe_tuple' => [
                'required',
                ['max', '50'],
            ],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

class ConditionalTupleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->requiredIf('role', 'admin')->max(255),
            'notes' => FluentRule::string()->excludeUnless('status', 'active')->nullable(),
            'phone' => FluentRule::string()->requiredWith('email'),
            'dynamic_max' => [
                'required',
                ['max', $this->getMaxValue()],
            ],
            'safe_tuple' => FluentRule::field()->required()->rule(['max', '50']),
        ];
    }
}

?>
