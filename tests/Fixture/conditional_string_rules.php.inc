<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class ConditionalFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required_if:role,admin|string|max:255',
            'backup_email' => 'required_with:email|email',
            'reason' => 'required_unless:status,active|string',
            'phone' => 'required_without:email|string',
            'start_date' => 'required|date|date_format:Y-m-d',
            'username' => 'required|string|alpha_dash|max:50',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ConditionalFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->requiredIf('role', 'admin')->max(255),
            'backup_email' => FluentRule::email()->requiredWith('email'),
            'reason' => FluentRule::string()->requiredUnless('status', 'active'),
            'phone' => FluentRule::string()->requiredWithout('email'),
            'start_date' => FluentRule::date()->required()->format('Y-m-d'),
            'username' => FluentRule::string()->required()->alphaDash()->max(50),
        ];
    }
}

?>
