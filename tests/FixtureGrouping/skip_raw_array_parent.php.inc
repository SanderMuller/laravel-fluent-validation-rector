<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class RawArrayParentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'interactions' => ['required', 'array', ['min', 1]],
            'interactions.*.type' => FluentRule::string()->required(),
            'interactions.*.name' => FluentRule::string()->required(),
        ];
    }
}

?>
