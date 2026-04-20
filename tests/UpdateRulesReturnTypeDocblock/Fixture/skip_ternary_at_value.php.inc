<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SkipTernaryAtValue extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => $this->has('admin') ? FluentRule::string()->required()->max(255) : FluentRule::string()->required(),
        ];
    }
}

?>
