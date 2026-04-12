<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SingleChildRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'wrapper' => FluentRule::array()->required(),
            'wrapper.*.value' => FluentRule::string()->required(),
            'config' => FluentRule::field()->nullable(),
            'config.enabled' => FluentRule::boolean()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SingleChildRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'wrapper' => FluentRule::array()->required()->each(['value' => FluentRule::string()->required()]),
            'config' => FluentRule::field()->nullable()->children(['enabled' => FluentRule::boolean()->required()]),
        ];
    }
}

?>
