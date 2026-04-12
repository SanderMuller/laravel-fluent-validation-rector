<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class SimpleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'nullable|numeric|min:0|max:120',
            'count' => 'required|integer|between:1,10',
            'status' => 'required|string|in:active,inactive,pending',
            'password' => 'required|string|min:8|confirmed',
            'bio' => 'nullable|string',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SimpleFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
            'email' => FluentRule::email()->required(),
            'age' => FluentRule::numeric()->nullable()->min(0)->max(120),
            'count' => FluentRule::integer()->required()->between(1, 10),
            'status' => FluentRule::string()->required()->in(['active', 'inactive', 'pending']),
            'password' => FluentRule::string()->required()->min(8)->confirmed(),
            'bio' => FluentRule::string()->nullable(),
        ];
    }
}

?>
