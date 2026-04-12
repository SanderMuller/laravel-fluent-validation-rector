<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class WildcardAbsorptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tags' => FluentRule::array()->required(),
            'tags.*' => FluentRule::field()->required(),
            'tags.*.label' => FluentRule::string()->required(),
            'tags.*.color' => FluentRule::string()->nullable(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class WildcardAbsorptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tags' => FluentRule::array()->required()->each(['label' => FluentRule::string()->required(), 'color' => FluentRule::string()->nullable()]),
        ];
    }
}

?>
