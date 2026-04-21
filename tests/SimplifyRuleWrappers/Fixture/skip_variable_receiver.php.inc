<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class SkipVariableReceiver
{
    public function rules(): array
    {
        // Variable root — receiver type cannot be inferred from a chain walk.
        // Phase 1 must bail without rewriting.
        $rule = FluentRule::string();

        return [
            'name' => $rule->rule(Rule::in(['a', 'b'])),
        ];
    }
}

?>
