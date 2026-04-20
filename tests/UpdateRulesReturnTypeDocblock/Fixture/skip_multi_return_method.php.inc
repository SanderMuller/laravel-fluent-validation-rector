<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SkipMultiReturnMethod extends FormRequest
{
    public function rules(): array
    {
        if ($this->user()?->isAdmin()) {
            return [
                'title' => FluentRule::string()->required(),
                'admin_note' => FluentRule::string()->required(),
            ];
        }

        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>
