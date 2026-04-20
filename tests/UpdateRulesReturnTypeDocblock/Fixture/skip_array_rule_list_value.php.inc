<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SkipArrayRuleListValue extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
            'answer' => [
                ['exclude_if', 'type', 'essay'],
                'nullable',
                'numeric',
            ],
        ];
    }
}

?>
