<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SkipConditionalTupleValue extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
            'admin_note' => [true, FluentRule::string()->nullable()],
        ];
    }
}

?>
