<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class NonRedundantWildcardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tags' => FluentRule::array()->required(),
            'tags.*' => FluentRule::string()->required()->min(2)->max(50),
            'tags.*.label' => FluentRule::string()->required(),
        ];
    }
}

?>
