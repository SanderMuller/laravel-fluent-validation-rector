<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;

class ConditionalArrayFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required_if:role,admin', 'string', 'max:255'],
            'backup_email' => ['required_with:email', 'email'],
            'reason' => ['required_unless:status,active', 'string'],
            'phone' => ['required_without:email', 'string'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ConditionalArrayFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->requiredIf('role', 'admin')->max(255),
            'backup_email' => FluentRule::email()->requiredWith('email'),
            'reason' => FluentRule::string()->requiredUnless('status', 'active'),
            'phone' => FluentRule::string()->requiredWithout('email'),
        ];
    }
}

?>
