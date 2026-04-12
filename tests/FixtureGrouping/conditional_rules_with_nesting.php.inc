<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ConditionalWithNestingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'interactions' => FluentRule::array()->required(),
            'interactions.*.type' => FluentRule::string()->required(),
            'interactions.*.end_time' => FluentRule::numeric()->requiredUnless('interactions.*.type', 'pause'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ConditionalWithNestingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'interactions' => FluentRule::array()->required()->each([
                'type' => FluentRule::string()->required(),
                'end_time' => FluentRule::numeric()->requiredUnless('interactions.*.type', 'pause'),
            ]),
        ];
    }
}

?>
