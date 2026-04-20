<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SkipMethodCallAtValue extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
            'password' => $this->passwordRules(),
        ];
    }

    private function passwordRules(): array
    {
        return ['required', 'confirmed'];
    }
}

?>
