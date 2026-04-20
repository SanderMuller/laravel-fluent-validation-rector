<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SkipMatchAtValue extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
            'mode' => match ($this->input('type')) {
                'int' => FluentRule::numeric()->integer()->required(),
                default => FluentRule::string()->required(),
            },
        ];
    }
}

?>
