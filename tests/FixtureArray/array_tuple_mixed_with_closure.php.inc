<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class TupleMixedWithClosureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                ['min', 5],
                ['max', 50],
                function (string $attribute, string $value, Closure $fail): void {
                    if (strlen($value) === 0) {
                        $fail('empty');
                    }
                },
            ],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use SanderMuller\FluentValidation\FluentRule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class TupleMixedWithClosureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->min(5)->max(50)->rule(function (string $attribute, string $value, Closure $fail): void {
                if (strlen($value) === 0) {
                    $fail('empty');
                }
            }),
        ];
    }
}

?>
