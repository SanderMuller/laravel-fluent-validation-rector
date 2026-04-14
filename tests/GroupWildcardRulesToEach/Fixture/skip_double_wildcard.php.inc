<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class DoubleWildcardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => FluentRule::array()->required(),
            'items.*.*.name' => FluentRule::string()->required(),
        ];
    }
}

?>
