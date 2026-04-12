<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class WrongParentTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => FluentRule::string()->required(),
            'items.*.name' => FluentRule::string()->required(),
        ];
    }
}

?>
