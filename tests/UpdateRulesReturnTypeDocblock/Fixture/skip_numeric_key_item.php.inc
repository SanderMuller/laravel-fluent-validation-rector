<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SkipNumericKeyItem extends FormRequest
{
    public function rules(): array
    {
        return [
            FluentRule::string()->required(),
            FluentRule::numeric()->required(),
        ];
    }
}

?>
