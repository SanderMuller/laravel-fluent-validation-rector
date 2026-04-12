<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;

class TupleBetweenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'display_name' => [
                'required',
                'string',
                ['between', 3, 100],
            ],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

class TupleBetweenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'display_name' => FluentRule::string()->required()->between(3, 100),
        ];
    }
}

?>
