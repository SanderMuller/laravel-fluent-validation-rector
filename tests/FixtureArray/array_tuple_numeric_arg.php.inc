<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;

class TupleNumericArgRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'author_notes' => [
                'nullable',
                'string',
                ['max', 65535],
            ],
            'tags' => [
                'array',
                ['max', 10],
            ],
            'postal_code' => [
                'required',
                'string',
                ['min', 4],
                ['max', 255],
            ],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class TupleNumericArgRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'author_notes' => FluentRule::string()->nullable()->max(65535),
            'tags' => FluentRule::array()->max(10),
            'postal_code' => FluentRule::string()->required()->min(4)->max(255),
        ];
    }
}

?>
