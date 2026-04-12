<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class MissingParentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
            'items.*.title' => FluentRule::string()->required(),
            'items.*.qty' => FluentRule::numeric()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class MissingParentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
            'items' => FluentRule::array()->nullable()->each(['title' => FluentRule::string()->required(), 'qty' => FluentRule::numeric()->required()]),
        ];
    }
}

?>
