<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SkipRulesBuilderVariant extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'title' => FluentRule::string()->required(),
        ];

        $rules['body'] = FluentRule::string()->nullable();

        return $rules;
    }
}

?>
