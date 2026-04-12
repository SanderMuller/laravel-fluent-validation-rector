<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class MixedEachChildrenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'data' => FluentRule::array()->required(),
            'data.meta' => FluentRule::string()->nullable(),
            'data.*.name' => FluentRule::string()->required(),
            'data.*.email' => FluentRule::email()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class MixedEachChildrenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'data' => FluentRule::array()->required()->each([
                'name' => FluentRule::string()->required(),
                'email' => FluentRule::email()->required(),
            ])->children([
                'meta' => FluentRule::string()->nullable(),
            ]),
        ];
    }
}

?>
