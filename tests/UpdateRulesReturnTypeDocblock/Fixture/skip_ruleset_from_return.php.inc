<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

class SkipRulesetFromReturn extends FormRequest
{
    public function rules(): array
    {
        return RuleSet::from([
            'title' => FluentRule::string()->required(),
        ])->all();
    }
}

?>
